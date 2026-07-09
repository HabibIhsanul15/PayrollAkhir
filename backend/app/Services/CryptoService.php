<?php

namespace App\Services;

use App\Exceptions\CryptoException;
use App\Models\CryptoKey;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class CryptoService
{
    // =====================================================
    // AES-128 KEY (untuk mode AES-only)
    // =====================================================
    private static function key(): string
    {
        $key = (string) env('AES_KEY_128', '');
        if (strlen($key) !== 16) {
            throw new RuntimeException("AES_KEY_128 harus 16 karakter/byte.");
        }
        return $key;
    }

    public static function keyId(): string
    {
        return 'aes128:' . substr(hash('sha256', self::key()), 0, 12);
    }

    // =====================================================
    // MODE (optional)
    // =====================================================
    public static function salaryStorageMode(): string
    {
        return strtoupper((string) config('crypto.salary_storage_mode', 'TRANSITION'));
    }

    public static function writeAlg(): string
    {
        return strtoupper((string) config('crypto.payroll_write_alg', 'AES'));
    }

    public static function readMode(): string
    {
        return strtoupper((string) config('crypto.payroll_read_mode', 'TRANSITION'));
    }

    // =====================================================
    // ROUTER decryptByAlg (AES/RSA saja)
    // HYBRID tidak bisa decrypt dari 1 field karena butuh dek_enc + enc_meta.
    // =====================================================
    public static function decryptByAlg(?string $payload, ?string $alg): ?string
    {
        if (!$payload) return null;

        $alg = strtoupper((string) ($alg ?: 'AES'));

        return match ($alg) {
            'AES' => self::decryptAESGCM($payload),
            'RSA' => self::decryptRSA($payload),
            'HYBRID' => throw new CryptoException("HYBRID butuh dek_enc + enc_meta payroll. Gunakan decryptHybridPayrollRow()."),
            default => throw new CryptoException("Decrypt not implemented for alg: {$alg}"),
        };
    }

    // =====================================================
    // READ helper (AES/RSA only)
    // =====================================================
    public static function readEncryptedOrPlain($enc, $plain, string $alg = 'AES')
    {
        $mode = self::readMode();
        $alg  = strtoupper((string) $alg);

        if ($mode === 'CIPHER_ONLY') {
            return match ($alg) {
                'AES' => self::decryptAESGCM($enc),
                'RSA' => self::decryptRSA($enc),
                'HYBRID' => throw new CryptoException("HYBRID butuh dek_enc + enc_meta payroll. Gunakan decryptHybridPayrollRow()."),
                default => throw new RuntimeException("Algoritma tidak dikenali: {$alg}"),
            };
        }

        if ($plain !== null && $plain !== '') return $plain;

        if ($enc) {
            return match ($alg) {
                'AES' => self::decryptAESGCM($enc),
                'RSA' => self::decryptRSA($enc),
                default => null,
            };
        }

        return null;
    }

    public static function readEncryptedOrPlainSafe(?string $enc, $plain, string $alg = 'AES'): ?string
    {
        try {
            return self::readEncryptedOrPlain($enc, $plain, $alg);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =====================================================
    // AES-128-GCM (AES-only, key dari env)
    // Output: base64(iv(12) + tag(16) + cipher)
    // =====================================================
    public static function encryptAESGCM(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            'aes-128-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($cipher === false) {
            throw new CryptoException('Encrypt failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public static function decryptAESGCM(?string $b64): ?string
    {
        if (!$b64) return null;

        $raw = base64_decode($b64, true);
        if ($raw === false) throw new CryptoException("Ciphertext base64 invalid.");
        if (strlen($raw) < 28) throw new CryptoException("Ciphertext format invalid (too short).");

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plain = openssl_decrypt(
            $cipher,
            'aes-128-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plain === false) {
            throw new CryptoException("Decrypt failed: " . openssl_error_string());
        }

        return $plain;
    }

    public static function safeDecryptAESGCM(?string $b64): ?string
    {
        if ($b64 === null || $b64 === '') return null;

        try {
            return self::decryptAESGCM($b64);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // =====================================================
    // RSA (mode RSA-only)
    // Payload: JSON {rsa_key_id, ct(base64)}
    // =====================================================
    public static function encryptRSA(string $plain): string
    {
        $key = self::activeRsaKey();

        $ok = openssl_public_encrypt(
            $plain,
            $cipherBin,
            $key->public_key_pem,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$ok) {
            throw new CryptoException('RSA encrypt failed: ' . openssl_error_string());
        }

        return json_encode([
            'v' => 1,
            'alg' => 'RSA-2048-OAEP',
            'rsa_key_id' => (int) $key->id,
            'ct' => base64_encode($cipherBin),
        ], JSON_UNESCAPED_SLASHES);
    }

    public static function decryptRSA(?string $payloadJson): ?string
    {
        if (!$payloadJson) return null;

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || empty($payload['ct']) || empty($payload['rsa_key_id'])) {
            throw new CryptoException("RSA payload invalid.");
        }

        $keyId = (int) $payload['rsa_key_id'];

        if (self::$activeRsaKeyCache && self::$activeRsaKeyCache->id === $keyId) {
            $privatePem = self::activePrivatePem();
        } else {
            $key = CryptoKey::findOrFail($keyId);
            $privatePem = Crypt::decryptString($key->private_key_pem_enc);
        }

        $cipherBin = base64_decode($payload['ct'], true);
        if ($cipherBin === false) {
            throw new CryptoException("RSA ciphertext base64 invalid.");
        }

        $ok = openssl_private_decrypt(
            $cipherBin,
            $plain,
            $privatePem,
            OPENSSL_PKCS1_OAEP_PADDING
        );

        if (!$ok) {
            throw new CryptoException("RSA decrypt failed: " . openssl_error_string());
        }

        return $plain;
    }

    public static function rsaKeyId(): string
    {
        $key = CryptoKey::where('status', 'active')->firstOrFail();
        return 'rsa2048:' . $key->id;
    }

    // =====================================================
    // HYBRID RSA–AES (nama tetap "HYBRID")
    // - DEK (16 bytes) dibuat 1x per payroll row
    // - Field dienkripsi AES-128-GCM (DEK)
    // - DEK dibungkus RSA (OAEP) => payrolls.dek_enc (base64)
    // - enc_meta simpan rsa_key_id dkk (payrolls.enc_meta JSON)
    // =====================================================
    public static function hybridKeyId(): string
    {
        $key = CryptoKey::where('status', 'active')->firstOrFail();
        return 'hybrid:rsa2048:' . $key->id;
    }

    private static function aesGcmEncryptWithKey(string $plain, string $key16): string
    {
        if (strlen($key16) !== 16) throw new RuntimeException("DEK harus 16 bytes.");

        $iv = random_bytes(12);
        $tag = '';

        $ct = openssl_encrypt($plain, 'aes-128-gcm', $key16, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) throw new CryptoException('AES encrypt failed: ' . openssl_error_string());

        return base64_encode($iv . $tag . $ct);
    }

    private static function aesGcmDecryptWithKey(?string $b64, string $key16): ?string
    {
        if (!$b64) return null;
        if (strlen($key16) !== 16) throw new RuntimeException("DEK harus 16 bytes.");

        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) < 28) throw new CryptoException('Ciphertext format invalid.');

        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);

        $plain = openssl_decrypt($ct, 'aes-128-gcm', $key16, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) throw new CryptoException('AES decrypt failed: ' . openssl_error_string());

        return $plain;
    }

    public static function encryptHybridPayroll(array $plainFields): array
    {
        $rsa = self::activeRsaKey();

        // 1) DEK
        $dek16 = random_bytes(16);

        // 2) Encrypt fields pakai DEK
        $enc = [];
        foreach (['gaji_pokok','tunjangan','potongan','total','catatan'] as $k) {
            $val = (string)($plainFields[$k] ?? '');
            $enc[$k . '_enc'] = self::aesGcmEncryptWithKey($val, $dek16);
        }

        // Additive for new fields
        foreach (['total_allowances', 'total_deductions'] as $k) {
            if (array_key_exists($k, $plainFields) && $plainFields[$k] !== null) {
                $enc[$k . '_enc'] = self::aesGcmEncryptWithKey((string)$plainFields[$k], $dek16);
            }
        }

        // 3) Bungkus DEK pakai RSA
        $ok = openssl_public_encrypt($dek16, $dekWrappedBin, $rsa->public_key_pem, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok) throw new CryptoException('HYBRID RSA encrypt DEK failed: ' . openssl_error_string());

        return [
            'dek_enc' => base64_encode($dekWrappedBin),
            'enc_meta' => [
                'v' => 2,
                'alg' => 'HYBRID',
                'rsa_key_id' => (int)$rsa->id,
                'dek_wrap' => 'RSA-2048-OAEP',
                'data_cipher' => 'AES-128-GCM',
            ],
            'fields' => $enc,
        ];
    }

    public static function decryptHybridPayrollRow(array $row): array
    {
        $meta = $row['enc_meta'] ?? null;
        if (is_string($meta)) $meta = json_decode($meta, true);
        if (!is_array($meta)) throw new CryptoException('enc_meta invalid.');

        $rsaKeyId = (int)($meta['rsa_key_id'] ?? 0);
        if ($rsaKeyId <= 0) throw new CryptoException('enc_meta missing rsa_key_id.');

        $dekEncB64 = (string)($row['dek_enc'] ?? '');
        if ($dekEncB64 === '') throw new CryptoException('dek_enc empty.');

        // private pem
        if (self::$activeRsaKeyCache && self::$activeRsaKeyCache->id === $rsaKeyId) {
            $privatePem = self::activePrivatePem();
        } else {
            $key = CryptoKey::findOrFail($rsaKeyId);
            $privatePem = Crypt::decryptString($key->private_key_pem_enc);
        }

        // 1) RSA decrypt DEK (1x)
        $dekWrappedBin = base64_decode($dekEncB64, true);
        if ($dekWrappedBin === false) throw new CryptoException('dek_enc base64 invalid.');

        $ok = openssl_private_decrypt($dekWrappedBin, $dek16, $privatePem, OPENSSL_PKCS1_OAEP_PADDING);
        if (!$ok || strlen($dek16) !== 16) {
            throw new CryptoException('HYBRID RSA decrypt DEK failed: ' . openssl_error_string());
        }

        // 2) AES decrypt fields
        $out = [];
        foreach (['gaji_pokok','tunjangan','potongan','total','catatan'] as $k) {
            $out[$k] = self::aesGcmDecryptWithKey($row[$k . '_enc'] ?? null, $dek16);
        }

        // Additive for new fields
        foreach (['total_allowances', 'total_deductions'] as $k) {
            if (!empty($row[$k . '_enc'])) {
                $out[$k] = self::aesGcmDecryptWithKey($row[$k . '_enc'], $dek16);
            } else {
                $out[$k] = null;
            }
        }

        return $out;
    }

    // =====================================================
    // RSA key cache
    // =====================================================
    private static ?CryptoKey $activeRsaKeyCache = null;
    private static ?string $activePrivatePemCache = null;

    private static function activeRsaKey(): CryptoKey
    {
        if (self::$activeRsaKeyCache) return self::$activeRsaKeyCache;

        $key = CryptoKey::where('status', 'active')->firstOrFail();
        self::$activeRsaKeyCache = $key;
        return $key;
    }

    private static function activePrivatePem(): string
    {
        if (self::$activePrivatePemCache) return self::$activePrivatePemCache;

        $key = self::activeRsaKey();
        self::$activePrivatePemCache = Crypt::decryptString($key->private_key_pem_enc);
        return self::$activePrivatePemCache;
    }
}
