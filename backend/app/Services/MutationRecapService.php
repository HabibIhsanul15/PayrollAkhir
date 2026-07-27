<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MonthlyRecap;
use App\Models\MutationRequest;
use App\Models\SalaryProfile;
use App\Support\PayrollPeriodResolver;

class MutationRecapService
{
    public function pendingForPeriod(int $employeeId, string $periodMonth): ?MutationRequest
    {
        $period = PayrollPeriodResolver::forMonth($periodMonth);

        return MutationRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->whereDate('effective_date', $period->start_date)
            ->latest('id')
            ->first();
    }

    /**
     * Rekap yang masih draft dapat sudah dibuat sebelum Director menyetujui
     * mutasi. Karena mutasi selalu efektif pada awal periode payroll, seluruh
     * rekap draft periode tersebut harus memakai profil target yang disetujui.
     */
    public function syncDraftRecapsToProfile(Employee $employee, string $periodMonth, SalaryProfile $profile): void
    {
        $recaps = MonthlyRecap::query()
            ->where('employee_id', $employee->id)
            ->where('period_month', $periodMonth)
            ->where('is_finalized', false)
            ->lockForUpdate()
            ->get();

        if ($recaps->isEmpty()) {
            return;
        }

        $fields = [
            'wfo_days',
            'wfh_days',
            'out_of_town_days',
            'business_trips',
            'training_days',
            'overtime_hours',
            'late_count',
            'total_mandays',
        ];

        $payload = ['salary_profile_id' => $profile->id];
        foreach ($fields as $field) {
            $payload[$field] = (int) $recaps->sum($field);
        }

        $targetRecap = $recaps->first(
            fn (MonthlyRecap $recap) => (int) $recap->salary_profile_id === (int) $profile->id
        );
        $targetRecap ??= $recaps->first();

        $targetRecap->update($payload);

        MonthlyRecap::query()
            ->whereIn('id', $recaps->pluck('id')->filter(fn (int $id) => $id !== $targetRecap->id))
            ->delete();
    }
}
