<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\CryptoService;
use App\Models\Payroll;
use App\Models\SalaryProfile;
use App\Models\Employee;

class EncryptLegacyData extends Command
{
    protected $signature = 'encrypt:legacy-data
                            {--only= : Pilih: payrolls|salary_profiles|employees}
                            {--dry-run : Hanya hitung data, tidak menulis}';

    protected $description = 'Encrypt legacy plaintext rows into *_enc columns (AES-GCM)';

    public function handle(): int
    {
        $only = $this->option('only');
        $dry  = (bool) $this->option('dry-run');

        $this->info('=== Encrypt Legacy Data ===');
        $this->info('Key ID: ' . CryptoService::keyId());
        if ($dry) $this->warn('DRY RUN: tidak ada update ke database.');

        if (!$only || $only === 'payrolls') {
            $this->encryptPayrolls($dry);
        }
        if (!$only || $only === 'salary_profiles') {
            $this->encryptSalaryProfiles($dry);
        }
        if (!$only || $only === 'employees') {
            $this->encryptEmployees($dry);
        }

        $this->info('Selesai.');
        return self::SUCCESS;
    }

    private function encryptPayrolls(bool $dry): void
    {
        $this->line('');
        $this->info('[1/3] payrolls');

        $rows = Payroll::query()
            ->where(function ($q) {
                $q->whereNull('gaji_pokok_enc')
                  ->orWhereNull('total_enc');
            })
            ->get();

        $this->info('Target rows: ' . $rows->count());

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $p) {
            $gaji  = (float) ($p->gaji_pokok ?? 0);
            $tunj  = (float) ($p->tunjangan ?? 0);
            $pot   = (float) ($p->potongan ?? 0);
            $total = (float) ($p->total ?? ($gaji + $tunj - $pot));
            $cat   = $p->catatan;

            $update = [
                'gaji_pokok_enc' => CryptoService::encryptAESGCM((string)$gaji),
                'tunjangan_enc'  => CryptoService::encryptAESGCM((string)$tunj),
                'potongan_enc'   => CryptoService::encryptAESGCM((string)$pot),
                'total_enc'      => CryptoService::encryptAESGCM((string)$total),
                'catatan_enc'    => !empty($cat) ? CryptoService::encryptAESGCM((string)$cat) : null,
                'salary_alg'     => 'AES',
                'salary_key_id'  => CryptoService::keyId(),
            ];

            if (!$dry) {
                $p->update($update);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('Done payrolls.');
    }

    private function encryptSalaryProfiles(bool $dry): void
    {
        $this->line('');
        $this->info('[2/3] salary_profiles');

        $rows = SalaryProfile::query()
            ->where(function ($q) {
                $q->whereNull('base_salary_enc')
                  ->orWhereNull('allowance_fixed_enc')
                  ->orWhereNull('deduction_fixed_enc');
            })
            ->get();

        $this->info('Target rows: ' . $rows->count());

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $s) {
            $base = (float) ($s->base_salary ?? 0);
            $allow= (float) ($s->allowance_fixed ?? 0);
            $ded  = (float) ($s->deduction_fixed ?? 0);

            $daily = $s->daily_rate !== null ? (float) $s->daily_rate : null;
            $ot    = $s->overtime_rate_per_hour !== null ? (float) $s->overtime_rate_per_hour : null;
            $late  = $s->late_penalty_per_minute !== null ? (float) $s->late_penalty_per_minute : null;

            $update = [
                'base_salary_enc' => CryptoService::encryptAESGCM((string)$base),
                'allowance_fixed_enc' => CryptoService::encryptAESGCM((string)$allow),
                'deduction_fixed_enc' => CryptoService::encryptAESGCM((string)$ded),

                'daily_rate_enc' => $daily !== null ? CryptoService::encryptAESGCM((string)$daily) : null,
                'overtime_rate_per_hour_enc' => $ot !== null ? CryptoService::encryptAESGCM((string)$ot) : null,
                'late_penalty_per_minute_enc' => $late !== null ? CryptoService::encryptAESGCM((string)$late) : null,

                'salary_alg'    => 'AES',
                'salary_key_id' => CryptoService::keyId(),
            ];

            if (!$dry) {
                $s->update($update);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('Done salary_profiles.');
    }

    private function encryptEmployees(bool $dry): void
    {
        $this->line('');
        $this->info('[3/3] employees');

        $rows = Employee::query()
            ->where(function ($q) {
                $q->whereNull('nik_enc')
                  ->orWhereNull('npwp_enc')
                  ->orWhereNull('bank_account_number_enc');
            })
            ->get();

        $this->info('Target rows: ' . $rows->count());

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $e) {
            $update = [
                'nik_enc' => !empty($e->nik) ? CryptoService::encryptAESGCM((string)$e->nik) : null,
                'npwp_enc' => !empty($e->npwp) ? CryptoService::encryptAESGCM((string)$e->npwp) : null,
                'bank_account_number_enc' => !empty($e->bank_account_number)
                    ? CryptoService::encryptAESGCM((string)$e->bank_account_number)
                    : null,

                // opsional
                'phone_enc' => !empty($e->phone) ? CryptoService::encryptAESGCM((string)$e->phone) : null,
                'address_enc' => !empty($e->address) ? CryptoService::encryptAESGCM((string)$e->address) : null,

                'pii_alg'    => 'AES',
                'pii_key_id' => CryptoService::keyId(),
            ];

            if (!$dry) {
                $e->update($update);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('Done employees.');
    }
}
