<?php

return [
    // TRANSITION | CIPHER_ONLY
    'salary_storage_mode' => env('SALARY_STORAGE_MODE', 'CIPHER_ONLY'),

    // TRANSITION | CIPHER_ONLY
    'payroll_read_mode' => env('PAYROLL_READ_MODE', 'CIPHER_ONLY'),

    // Semua data sensitif baru wajib memakai RSA-2048-OAEP + AES-128-GCM.
    'payroll_write_alg' => 'HYBRID',

    // AES key env name (biar rapi kalau suatu saat ganti)
    'aes_key_env' => env('AES_KEY_ENV', 'AES_KEY_128'),
];
