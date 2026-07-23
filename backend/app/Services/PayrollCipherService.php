<?php

namespace App\Services;

use App\Models\Payroll;

class PayrollCipherService
{
    public function encrypt(array $plain): array
    {
        $alg = strtoupper(CryptoService::writeAlg());
        $plain = array_merge([
            'gaji_pokok' => 0,
            'tunjangan' => 0,
            'potongan' => 0,
            'total' => 0,
        ], $plain);

        if ($alg === 'HYBRID') {
            $pack = CryptoService::encryptHybridPayroll($plain);

            return [
                'alg' => $alg,
                'key_id' => CryptoService::hybridKeyId(),
                'fields' => $pack['fields'],
                'dek_enc' => $pack['dek_enc'],
                'enc_meta' => $pack['enc_meta'],
            ];
        }

        $encrypt = fn (string $value) => $alg === 'RSA'
            ? CryptoService::encryptRSA($value)
            : CryptoService::encryptAESGCM($value);
        $fields = [];

        foreach (['gaji_pokok', 'tunjangan', 'potongan', 'total'] as $field) {
            $fields[$field.'_enc'] = $encrypt((string) $plain[$field]);
        }
        return [
            'alg' => $alg,
            'key_id' => $alg === 'RSA' ? CryptoService::rsaKeyId() : CryptoService::keyId(),
            'fields' => $fields,
            'dek_enc' => null,
            'enc_meta' => null,
        ];
    }

    public function decrypt(Payroll $payroll): array
    {
        $alg = strtoupper((string) ($payroll->salary_alg ?? 'AES'));

        if ($alg === 'HYBRID') {
            return CryptoService::decryptHybridPayrollRow([
                'dek_enc' => $payroll->dek_enc,
                'enc_meta' => $payroll->enc_meta,
                'gaji_pokok_enc' => $payroll->gaji_pokok_enc,
                'tunjangan_enc' => $payroll->tunjangan_enc,
                'potongan_enc' => $payroll->potongan_enc,
                'total_enc' => $payroll->total_enc,
            ]);
        }

        $result = [];
        foreach (['gaji_pokok', 'tunjangan', 'potongan', 'total'] as $field) {
            $result[$field] = CryptoService::readEncryptedOrPlainSafe(
                $payroll->{$field.'_enc'},
                $payroll->{$field},
                $alg
            );
        }

        return $result;
    }
}
