<?php

namespace App\Console\Commands;

use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MakePayrollPeriod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:period 
                            {month : Bulan periode (contoh: 2026-07)} 
                            {--start= : Tanggal mulai (contoh: 2026-06-28)} 
                            {--end= : Tanggal akhir (contoh: 2026-07-27)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membuat master data Payroll Period secara manual dari console';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = $this->argument('month');
        $start = $this->option('start');
        $end = $this->option('end');

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Format bulan salah! Gunakan format YYYY-MM (contoh: 2026-07)");
            return;
        }

        if (!$start || !$end) {
            // Default 28 to 27
            $date = Carbon::createFromFormat('Y-m', $month);
            $end = $date->copy()->day(27)->format('Y-m-d');
            $start = $date->copy()->subMonth()->day(28)->format('Y-m-d');
        }

        $period = PayrollPeriod::updateOrCreate(
            ['period_month' => $month],
            [
                'name' => 'Periode Gaji ' . Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'),
                'start_date' => $start,
                'end_date' => $end,
                'status' => 'open'
            ]
        );

        $this->info("Berhasil menyimpan periode {$period->period_month} ({$period->start_date} s/d {$period->end_date}).");
    }
}
