<?php

namespace App\Services\Crypto;

use Illuminate\Support\Facades\Crypt;

class AppKeyProtector
{
    public static function enc(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    public static function dec(string $cipher): string
    {
        return Crypt::decryptString($cipher);
    }
}
