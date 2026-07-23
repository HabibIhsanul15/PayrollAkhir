<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'period_month',
        'name',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public static function currentMonth(): string
    {
        $date = now();

        return ($date->day >= 28 ? $date->addMonth() : $date)->format('Y-m');
    }

    public static function forMonth(string $periodMonth): self
    {
        $month = Carbon::createFromFormat('Y-m', $periodMonth)->startOfMonth();
        $start = $month->copy()->subMonth()->day(28);
        $end = $month->copy()->day(27);

        return static::updateOrCreate(
            ['period_month' => $month->format('Y-m')],
            [
                'name' => 'Periode Gaji '.$month->translatedFormat('F Y'),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]
        );
    }

    public static function forDate(Carbon|string $date): self
    {
        $date = Carbon::parse($date);
        $periodMonth = $date->day >= 28
            ? $date->copy()->addMonth()->format('Y-m')
            : $date->format('Y-m');

        return static::forMonth($periodMonth);
    }

    public static function ensureUpcoming(int $months = 12): void
    {
        $month = Carbon::createFromFormat('Y-m', static::currentMonth())->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            static::forMonth($month->copy()->addMonths($i)->format('Y-m'));
        }
    }
}
