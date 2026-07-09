<?php

return [
    // TRANSITION | CIPHER_ONLY
    'salary_storage_mode' => env('SALARY_STORAGE_MODE', 'CIPHER_ONLY'),

    // TRANSITION | CIPHER_ONLY
    'payroll_read_mode' => env('PAYROLL_READ_MODE', 'CIPHER_ONLY'),

    // AES | RSA | HYBRID
    'payroll_write_alg' => env('PAYROLL_WRITE_ALG', 'AES'),

    // AES key env name (biar rapi kalau suatu saat ganti)
    'aes_key_env' => env('AES_KEY_ENV', 'AES_KEY_128'),
];
