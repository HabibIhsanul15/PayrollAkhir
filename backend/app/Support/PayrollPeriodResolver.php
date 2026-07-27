<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Aturan periode payroll: tanggal 28 bulan sebelumnya sampai tanggal 27 bulan berjalan.
 *
 * Karena cut-off bersifat tetap, periode tidak perlu disimpan sebagai master data.
 */
final class PayrollPeriodResolver
{
    private const START_DAY = 28;

    private const END_DAY = 27;

    public static function currentMonth(): string
    {
        $date = now();

        return ($date->day >= self::START_DAY ? $date->addMonth() : $date)->format('Y-m');
    }

    public static function forMonth(string $periodMonth): PayrollPeriodRange
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodMonth)) {
            throw new InvalidArgumentException('Format bulan periode harus YYYY-MM.');
        }

        $month = Carbon::createFromFormat('!Y-m', $periodMonth)->startOfMonth();
        $start = $month->copy()->subMonth()->day(self::START_DAY);
        $end = $month->copy()->day(self::END_DAY);

        return new PayrollPeriodRange(
            period_month: $month->format('Y-m'),
            name: 'Periode Gaji '.$month->translatedFormat('F Y'),
            start_date: $start,
            end_date: $end,
        );
    }

    public static function forDate(Carbon|string $date): PayrollPeriodRange
    {
        $date = Carbon::parse($date);
        $periodMonth = $date->day >= self::START_DAY
            ? $date->copy()->addMonth()->format('Y-m')
            : $date->format('Y-m');

        return self::forMonth($periodMonth);
    }
}
