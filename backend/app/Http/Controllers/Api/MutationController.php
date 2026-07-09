<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\SalaryProfile;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\CryptoService;

class MutationController extends Controller
{
    /**
     * POST /api/employees/{id}/mutate
     */
    public function store(Request $request, $id)
    {
        $user = $request->user();

        // Hanya HCGA dan Director yang boleh memutasi
        if (!in_array($user->role, ['hcga', 'director'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'mutation_type' => ['required', 'in:promotion,demotion,mutation'],
            'grade_id' => ['required', 'exists:grades,id'],
            'position_allowance' => ['required', 'numeric', 'min:0'],
            'mandays_rate' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $effectiveDate = Carbon::parse($data['effective_from'])->startOfDay();

        // 1. Tutup JobHistory yang aktif (jika ada dan belum ditutup, atau yg terakhir)
        $currentJob = JobHistory::where('employee_id', $employee->id)
            ->whereNull('end_date')
            ->orderBy('start_date', 'desc')
            ->first();

        if ($currentJob) {
            $currentJob->update([
                'end_date' => $effectiveDate->copy()->subDay()->format('Y-m-d')
            ]);
        }

        // 2. Buat JobHistory baru
        JobHistory::create([
            'employee_id' => $employee->id,
            'grade_id' => $data['grade_id'],
            'start_date' => $effectiveDate->format('Y-m-d'),
            'status' => 'active',
            'notes' => ($data['mutation_type'] === 'promotion' ? 'Promosi: ' : 
                        ($data['mutation_type'] === 'demotion' ? 'Demosi: ' : 'Mutasi: ')) . ($data['notes'] ?? ''),
        ]);

        // 3. Buat SalaryProfile baru (source of truth untuk payroll)
        $piiAlg = strtoupper((string) ($employee->pii_alg ?? 'AES'));

        $encPII = function (string $v) use ($piiAlg) {
            return $piiAlg === 'RSA'
                ? CryptoService::encryptRSA($v)
                : CryptoService::encryptAESGCM($v);
        };

        $base = (float) $data['position_allowance'];
        $mandays_rate = (float) $data['mandays_rate'];

        SalaryProfile::create([
            'employee_id' => $employee->id,
            'grade_id' => $data['grade_id'],
            'position' => $employee->position, 
            'position_allowance' => $base,
            'mandays_rate' => $mandays_rate,
            'allowance_fixed' => 0,
            'deduction_fixed' => 0,
            'effective_from' => $effectiveDate->format('Y-m-d'),
            'position_allowance_enc' => $base > 0 ? $encPII((string) $base) : null,
            'mandays_rate_enc' => $mandays_rate > 0 ? $encPII((string) $mandays_rate) : null,
            'salary_alg' => $piiAlg,
            'salary_key_id' => CryptoService::keyId(),
        ]);

        // 4. Update Employee grade (sebagai referensi cepat)
        $employee->update([
            'grade_id' => $data['grade_id']
        ]);

        return response()->json([
            'message' => 'Mutasi karyawan berhasil disimpan.',
        ]);
    }
}
