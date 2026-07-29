<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Services\MutationRecapService;
use App\Support\PayrollPeriodResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyRecapController extends Controller
{
    public function __construct(private MutationRecapService $mutationRecaps) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->user()->cannot('viewAny', MonthlyRecap::class)) {
            // Rekap hanya dipakai HCGA untuk input dan FAT untuk meninjau hasilnya.
            if (! in_array(strtolower($request->user()->role), ['hcga', 'fat'], true)) {
                abort(403);
            }
        }

        $periodMonth = $request->query('period_month', PayrollPeriodResolver::currentMonth());

        $recaps = MonthlyRecap::with('employee')
            ->where('period_month', $periodMonth)
            ->get();

        return response()->json($recaps);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (strtolower($request->user()->role) !== 'hcga') {
            abort(403, 'Hanya HCGA yang dapat menginput Rekap Bulanan.');
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_month' => 'required|date_format:Y-m',
            // Satu payroll period selalu memakai satu profile gaji. Profile
            // ditentukan server dari tanggal awal periode, bukan dari input client.
            'recaps' => 'required|array|size:1',
            'recaps.*.wfo_days' => 'integer|min:0',
            'recaps.*.wfh_days' => 'integer|min:0',
            'recaps.*.out_of_town_days' => 'integer|min:0',
            'recaps.*.training_days' => 'integer|min:0',
            'recaps.*.overtime_hours' => 'integer|min:0',
            'recaps.*.late_count' => 'integer|min:0',
        ]);

        $employeeId = $validated['employee_id'];
        $periodMonth = $validated['period_month'];

        $this->ensureNoPendingMutation($employeeId, $periodMonth);

        $payrollPeriod = PayrollPeriodResolver::forMonth($periodMonth);
        $maxDays = $payrollPeriod->start_date->diffInDays($payrollPeriod->end_date) + 1;
        $employee = Employee::findOrFail($employeeId);
        $salaryProfile = $employee->currentSalaryProfile($payrollPeriod->start_date->toDateString());

        if (! $salaryProfile) {
            throw ValidationException::withMessages([
                'employee_id' => 'Profil gaji yang berlaku pada awal periode payroll tidak ditemukan.',
            ]);
        }

        $recapData = $validated['recaps'][0];
        $totalSubmittedMandays = $this->recapMandays($recapData);

        if ($totalSubmittedMandays < 1) {
            throw ValidationException::withMessages([
                'recaps' => 'Total kehadiran minimal 1 hari. Jika pegawai tidak bekerja sepanjang periode, jangan buat rekap payroll untuk periode tersebut.',
            ]);
        }

        if ($totalSubmittedMandays > $maxDays) {
            throw ValidationException::withMessages([
                'recaps' => "Total hari dibayar ({$totalSubmittedMandays}) tidak boleh melebihi jumlah hari pada {$periodMonth} ({$maxDays} hari).",
            ]);
        }

        $lateCount = (int) ($recapData['late_count'] ?? 0);
        $wfoDays = (int) ($recapData['wfo_days'] ?? 0);

        if ($lateCount > $wfoDays) {
            throw ValidationException::withMessages([
                'recaps.0.late_count' => "Jumlah terlambat ({$lateCount}) tidak boleh melebihi Hari WFO ({$wfoDays}).",
            ]);
        }

        $existingRecaps = MonthlyRecap::query()
            ->where('employee_id', $employeeId)
            ->where('period_month', $periodMonth)
            ->get();

        if ($existingRecaps->contains('is_finalized', true)) {
            throw ValidationException::withMessages([
                'recaps' => 'Rekap untuk pegawai dan periode ini sudah dikirim ke Finance dan tidak dapat dibuat ulang.',
            ]);
        }

        if ($existingRecaps->count() > 1) {
            throw ValidationException::withMessages([
                'recaps' => 'Data rekap periode ini tidak valid karena memiliki lebih dari satu profile gaji. Hubungi administrator untuk konsolidasi data.',
            ]);
        }

        $data = [
            'employee_id' => $employeeId,
            'period_month' => $periodMonth,
            'salary_profile_id' => $salaryProfile->id,
            'wfo_days' => $recapData['wfo_days'] ?? 0,
            'wfh_days' => $recapData['wfh_days'] ?? 0,
            'out_of_town_days' => $recapData['out_of_town_days'] ?? 0,
            'training_days' => $recapData['training_days'] ?? 0,
            'overtime_hours' => $recapData['overtime_hours'] ?? 0,
            'late_count' => $recapData['late_count'] ?? 0,
            'total_mandays' => $totalSubmittedMandays,
        ];

        $recap = $existingRecaps->first();
        if ($recap) {
            $recap->update($data);
        } else {
            $recap = MonthlyRecap::create($data);
        }

        return response()->json([$recap], 201);
    }

    private function recapMandays(array $recapData): float
    {
        return (float) ($recapData['wfo_days'] ?? 0)
            + (float) ($recapData['wfh_days'] ?? 0)
            + (float) ($recapData['out_of_town_days'] ?? 0)
            + (float) ($recapData['training_days'] ?? 0);
    }

    /**
     * Finalize the recap so it can be processed by PayrollEngine.
     */
    public function finalize(Request $request, MonthlyRecap $recap)
    {
        if (strtolower($request->user()->role) !== 'hcga') {
            abort(403, 'Hanya HCGA yang dapat mengirim rekap ke Finance.');
        }

        if ($recap->is_finalized) {
            abort(422, 'Rekap ini sudah dikirim ke Finance.');
        }

        if ((int) $recap->total_mandays < 1) {
            abort(422, 'Rekap tanpa kehadiran tidak dapat dikirim ke Finance.');
        }

        $this->ensureNoPendingMutation($recap->employee_id, $recap->period_month);

        $recap->update([
            'is_finalized' => true,
            'finalized_by' => $request->user()->id,
            'finalized_at' => now(),
        ]);

        return response()->json($recap);
    }

    public function submitToFinance(Request $request)
    {
        if (strtolower($request->user()->role) !== 'hcga') {
            abort(403, 'Hanya HCGA yang dapat mengirim rekap ke Finance.');
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_month' => 'required|date_format:Y-m',
        ]);

        $this->ensureNoPendingMutation($validated['employee_id'], $validated['period_month']);

        $recaps = MonthlyRecap::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('period_month', $validated['period_month'])
            ->get();

        if ($recaps->isEmpty()) {
            throw ValidationException::withMessages([
                'recaps' => 'Belum ada draft rekap untuk pegawai dan periode ini.',
            ]);
        }

        if ($recaps->every(fn (MonthlyRecap $recap) => $recap->is_finalized)) {
            throw ValidationException::withMessages([
                'recaps' => 'Rekap untuk pegawai dan periode ini sudah dikirim ke Finance.',
            ]);
        }

        if ($recaps->sum('total_mandays') < 1) {
            throw ValidationException::withMessages([
                'recaps' => 'Rekap tanpa kehadiran tidak dapat dikirim ke Finance.',
            ]);
        }

        DB::transaction(function () use ($request, $validated) {
            MonthlyRecap::query()
                ->where('employee_id', $validated['employee_id'])
                ->where('period_month', $validated['period_month'])
                ->where('is_finalized', false)
                ->update([
                    'is_finalized' => true,
                    'finalized_by' => $request->user()->id,
                    'finalized_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return response()->json(
            MonthlyRecap::with('employee')
                ->where('employee_id', $validated['employee_id'])
                ->where('period_month', $validated['period_month'])
                ->get()
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, MonthlyRecap $recap)
    {
        if (strtolower($request->user()->role) !== 'hcga') {
            abort(403, 'Hanya HCGA yang dapat menghapus data.');
        }

        if ($recap->is_finalized) {
            abort(422, 'Tidak dapat menghapus rekap yang sudah dikirim ke Finance.');
        }

        $recap->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function ensureNoPendingMutation(int $employeeId, string $periodMonth): void
    {
        $mutation = $this->mutationRecaps->pendingForPeriod($employeeId, $periodMonth);
        if ($mutation) {
            abort(409, 'Rekap absensi periode ini dikunci karena pengajuan promosi/demosi masih menunggu persetujuan Direktur.');
        }
    }
}
