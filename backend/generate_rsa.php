<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$config = [
    "digest_alg" => "sha256",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
];

// Create the private and public key
$res = openssl_pkey_new($config);
openssl_pkey_export($res, $privKey);
$pubKey = openssl_pkey_get_details($res)["key"];

$key = \App\Models\CryptoKey::create([
    'name' => 'RSA Master Key 1',
    'status' => 'active',
    'public_key_pem' => $pubKey,
    'private_key_pem_enc' => \Illuminate\Support\Facades\Crypt::encryptString($privKey),
]);

echo "Created RSA key ID: " . $key->id . "\n";
