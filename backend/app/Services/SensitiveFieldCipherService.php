<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Adapter untuk record selain payroll yang juga memakai format hybrid.
 * Nilai lama AES/RSA tetap dapat dibaca saat migrasi berjalan.
 */
class SensitiveFieldCipherService
{
    /**
     * @param array<string, mixed> $plainFields key tanpa suffix _enc
     * @return array<string, mixed>
     */
    public function encryptAttributes(
        array $plainFields,
        string $algorithmColumn = 'salary_alg',
        string $keyIdColumn = 'salary_key_id'
    ): array {
        $attributes = [];
        $toEncrypt = [];

        foreach ($plainFields as $field => $value) {
            $attributes[$field . '_enc'] = null;
            if ($value !== null && $value !== '') {
                $toEncrypt[$field] = (string) $value;
            }
        }

        if ($toEncrypt === []) {
            return [
                ...$attributes,
                'dek_enc' => null,
                'enc_meta' => null,
                $algorithmColumn => 'HYBRID',
                $keyIdColumn => null,
            ];
        }

        $pack = CryptoService::encryptHybridFields($toEncrypt);

        return [
            ...$attributes,
            ...$pack['fields'],
            'dek_enc' => $pack['dek_enc'],
            'enc_meta' => $pack['enc_meta'],
            $algorithmColumn => 'HYBRID',
            $keyIdColumn => CryptoService::hybridKeyId(),
        ];
    }

    /**
     * @param Model|array<string, mixed> $record
     * @param array<int, string> $fields key tanpa suffix _enc
     * @return array<string, ?string>
     */
    public function decrypt(
        Model|array $record,
        array $fields,
        string $algorithmColumn = 'salary_alg'
    ): array {
        $attributes = $record instanceof Model ? $record->getAttributes() : $record;
        $alg = strtoupper((string) ($attributes[$algorithmColumn] ?? 'AES'));

        if ($alg === 'HYBRID') {
            $hasCiphertext = false;
            foreach ($fields as $field) {
                if (!empty($attributes[$field . '_enc'])) {
                    $hasCiphertext = true;
                    break;
                }
            }

            if (!$hasCiphertext) {
                return array_fill_keys($fields, null);
            }

            return CryptoService::decryptHybridFields($attributes, $fields);
        }

        $values = [];
        foreach ($fields as $field) {
            $ciphertext = $attributes[$field . '_enc'] ?? null;
            $values[$field] = $ciphertext
                ? CryptoService::decryptByAlg($ciphertext, $alg)
                : ($attributes[$field] ?? null);
        }

        return $values;
    }

    /**
     * @param Model|array<string, mixed> $record
     */
    public function decryptField(
        Model|array $record,
        string $field,
        string $algorithmColumn = 'salary_alg'
    ): ?string {
        return $this->decrypt($record, [$field], $algorithmColumn)[$field] ?? null;
    }
}
