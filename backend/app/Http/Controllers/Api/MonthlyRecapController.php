<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyRecap;
use App\Models\Employee;
use App\Models\SalaryProfile;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyRecapController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->user()->cannot('viewAny', MonthlyRecap::class)) {
            // Simplified permission check: HCGA and FAT can view
            if (!in_array(strtolower($request->user()->role), ['hcga', 'fat', 'director'])) {
                abort(403);
            }
        }

        $periodMonth = $request->query('period_month', PayrollPeriod::currentMonth());
        PayrollPeriod::forMonth($periodMonth);

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
            'recaps' => 'required|array|min:1',
            'recaps.*.salary_profile_id' => 'nullable|exists:salary_profiles,id',
            'recaps.*.wfo_days' => 'integer|min:0',
            'recaps.*.wfh_days' => 'integer|min:0',
            'recaps.*.out_of_town_days' => 'integer|min:0',
            'recaps.*.business_trips' => 'integer|min:0',
            'recaps.*.training_days' => 'integer|min:0',
            'recaps.*.overtime_hours' => 'integer|min:0',
            'recaps.*.late_count' => 'integer|min:0',
        ]);

        $employeeId = $validated['employee_id'];
        $periodMonth = $validated['period_month'];
        
        $payrollPeriod = PayrollPeriod::forMonth($periodMonth);
        $maxDays = $payrollPeriod->start_date->diffInDays($payrollPeriod->end_date) + 1;

        $totalSubmittedMandays = 0;
        $requestedProfileKeys = [];

        foreach ($validated['recaps'] as $index => $recapData) {
            $totalSubmittedMandays += $this->recapMandays($recapData);

            $salaryProfileId = $recapData['salary_profile_id'] ?? null;
            if ($salaryProfileId && ! SalaryProfile::where('id', $salaryProfileId)->where('employee_id', $employeeId)->exists()) {
                throw ValidationException::withMessages([
                    "recaps.{$index}.salary_profile_id" => 'Profil gaji tidak sesuai dengan karyawan yang dipilih.',
                ]);
            }

            $profileKey = $salaryProfileId === null ? 'without-profile' : (string) $salaryProfileId;
            if (in_array($profileKey, $requestedProfileKeys, true)) {
                throw ValidationException::withMessages([
                    "recaps.{$index}.salary_profile_id" => 'Satu profil gaji hanya boleh memiliki satu segmen rekap dalam periode yang sama.',
                ]);
            }

            $requestedProfileKeys[] = $profileKey;
        }

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

        $existingRecaps = MonthlyRecap::query()
            ->where('employee_id', $employeeId)
            ->where('period_month', $periodMonth)
            ->get();

        if ($existingRecaps->contains('is_finalized', true)) {
            throw ValidationException::withMessages([
                'recaps' => 'Rekap untuk karyawan dan periode ini sudah dikirim ke Finance dan tidak dapat dibuat ulang.',
            ]);
        }

        if ($existingRecaps->isNotEmpty()) {
            $existingProfileKeys = $existingRecaps
                ->map(fn (MonthlyRecap $recap) => $recap->salary_profile_id === null ? 'without-profile' : (string) $recap->salary_profile_id)
                ->sort()
                ->values()
                ->all();
            $submittedProfileKeys = collect($requestedProfileKeys)->sort()->values()->all();

            if ($existingProfileKeys !== $submittedProfileKeys) {
                throw ValidationException::withMessages([
                    'recaps' => 'Rekap untuk karyawan dan periode ini sudah ada. Gunakan Edit untuk memperbarui rekap yang sama.',
                ]);
            }
        }

        $createdRecaps = [];

        foreach ($validated['recaps'] as $recapData) {
            $totalMandays = $this->recapMandays($recapData);
            $lookup = [
                'employee_id' => $employeeId,
                'period_month' => $periodMonth,
                'salary_profile_id' => $recapData['salary_profile_id'] ?? null,
            ];
            $existingRecap = MonthlyRecap::where($lookup)->first();

            if ($existingRecap?->is_finalized) {
                throw ValidationException::withMessages([
                    'recaps' => 'Rekap yang sudah dikirim ke Finance tidak dapat diedit.',
                ]);
            }

            $data = [
                'employee_id' => $employeeId,
                'period_month' => $periodMonth,
                'salary_profile_id' => $recapData['salary_profile_id'] ?? null,
                'wfo_days' => $recapData['wfo_days'] ?? 0,
                'wfh_days' => $recapData['wfh_days'] ?? 0,
                'out_of_town_days' => $recapData['out_of_town_days'] ?? 0,
                'business_trips' => $recapData['business_trips'] ?? 0,
                'training_days' => $recapData['training_days'] ?? 0,
                'overtime_hours' => $recapData['overtime_hours'] ?? 0,
                'late_count' => $recapData['late_count'] ?? 0,
                'total_mandays' => $totalMandays,
            ];

            $createdRecaps[] = MonthlyRecap::updateOrCreate(
                $lookup,
                $data
            );
        }

        return response()->json($createdRecaps, 201);
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

        $recaps = MonthlyRecap::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('period_month', $validated['period_month'])
            ->get();

        if ($recaps->isEmpty()) {
            throw ValidationException::withMessages([
                'recaps' => 'Belum ada draft rekap untuk karyawan dan periode ini.',
            ]);
        }

        if ($recaps->every(fn (MonthlyRecap $recap) => $recap->is_finalized)) {
            throw ValidationException::withMessages([
                'recaps' => 'Rekap untuk karyawan dan periode ini sudah dikirim ke Finance.',
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

    public function prorataSuggestion(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_month' => 'required|date_format:Y-m',
        ]);

        $employeeId = $validated['employee_id'];
        $periodMonth = $validated['period_month'];

        $payrollPeriod = PayrollPeriod::forMonth($periodMonth);
        $startDate = Carbon::parse($payrollPeriod->start_date);
        $endDate = Carbon::parse($payrollPeriod->end_date);

        $totalDays = $startDate->diffInDays($endDate) + 1;

        $profiles = \App\Models\SalaryProfile::where('employee_id', $employeeId)
            ->where(function (mixed $q) use ($startDate, $endDate) {
                $q->whereBetween('effective_from', [$startDate, $endDate])
                  ->orWhere('effective_from', '<=', $startDate);
            })
            ->orderBy('effective_from', 'asc')
            ->get();

        if ($profiles->isEmpty()) {
            return response()->json([
                'suggestion' => [
                    [
                        'salary_profile_id' => null,
                        'days_suggested' => $totalDays,
                        'percentage' => 100
                    ]
                ]
            ]);
        }

        // If only 1 profile is active, no need to prorate
        if ($profiles->count() === 1 || $profiles->last()->effective_from <= $startDate) {
            return response()->json([
                'suggestion' => [
                    [
                        'salary_profile_id' => $profiles->last()->id,
                        'days_suggested' => $totalDays,
                        'percentage' => 100
                    ]
                ]
            ]);
        }

        // Calculate prorated days
        $suggestion = [];
        $currentStart = $startDate;

        foreach ($profiles as $index => $profile) {
            if ($profile->effective_from > $endDate) continue;

            $profileStart = max($currentStart, Carbon::parse($profile->effective_from));
            
            $nextProfile = $profiles[$index + 1] ?? null;
            $profileEnd = $nextProfile && $nextProfile->effective_from <= $endDate
                ? Carbon::parse($nextProfile->effective_from)->subDay()
                : $endDate;

            if ($profileStart > $profileEnd) continue;

            $days = $profileStart->diffInDays($profileEnd) + 1;
            
            $suggestion[] = [
                'salary_profile_id' => $profile->id,
                'days_suggested' => $days,
                'percentage' => round(($days / $totalDays) * 100, 2),
                'Position' => \App\Models\Position::find($profile->position_id)?->name
            ];

            $currentStart = $profileEnd->addDay();
        }

        return response()->json([
            'suggestion' => $suggestion,
            'total_period_days' => $totalDays
        ]);
    }
}
