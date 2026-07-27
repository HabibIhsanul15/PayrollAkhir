<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Nilai periode payroll hasil aturan cut-off tetap, tanpa penyimpanan database.
 */
final class PayrollPeriodRange
{
    public function __construct(
        public readonly string $period_month,
        public readonly string $name,
        public readonly Carbon $start_date,
        public readonly Carbon $end_date,
    ) {}

    /**
     * Payload yang dibutuhkan oleh API, misalnya detail pengajuan mutasi.
     *
     * @return array{period_month: string, name: string, start_date: string, end_date: string}
     */
    public function toArray(): array
    {
        return [
            'period_month' => $this->period_month,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
        ];
    }
}
