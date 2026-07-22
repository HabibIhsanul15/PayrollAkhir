<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SyncEffectiveEmployeeJobs extends Command
{
    protected $signature = 'employees:sync-effective-jobs {--date=}';

    protected $description = 'Sinkronkan referensi jabatan karyawan dengan salary profile yang sudah efektif.';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $updated = 0;

        Employee::query()->chunkById(100, function (mixed $employees) use ($date, &$updated) {
            foreach ($employees as $employee) {
                $profile = $employee->currentSalaryProfile($date);
                if (! $profile?->position_id) {
                    continue;
                }

                DB::transaction(function () use ($employee, $profile, $date, &$updated) {
                    $position = $profile->position;
                    $employee->update([
                        'position_id' => $profile->position_id,
                        'position' => $profile->position ?? $position?->name,
                    ]);

                    $employee->jobHistories()->update(['status' => 'inactive']);
                    $employee->jobHistories()
                        ->whereDate('start_date', '<=', $date)
                        ->where(function (Builder $query) use ($date) {
                            $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
                        })
                        ->orderByDesc('start_date')
                        ->limit(1)
                        ->update(['status' => 'active']);

                    $updated++;
                });
            }
        });

        $this->info("Jabatan efektif tersinkron untuk {$updated} karyawan.");

        return self::SUCCESS;
    }
}
