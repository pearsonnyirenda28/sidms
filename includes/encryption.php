<?php
function encryptFile(string $plaintext): array {
    $fileKey = random_bytes(32);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $fileKey, OPENSSL_RAW_DATA, $iv);
    $mkIv = random_bytes(16);
    $encFileKey = openssl_encrypt($fileKey, 'AES-256-CBC', MASTER_KEY, OPENSSL_RAW_DATA, $mkIv);
    return [
        'cipher' => base64_encode($cipher),
        'iv' => base64_encode($iv),
        'enc_key' => base64_encode($encFileKey),
        'key_iv' => base64_encode($mkIv)
    ];
}

function decryptFile(string $encB64, string $ivB64, string $encKeyB64, string $keyIvB64): string|false {
    $cipher = base64_decode($encB64);
    $iv = base64_decode($ivB64);
    $encKey = base64_decode($encKeyB64);
    $keyIv = base64_decode($keyIvB64);
    if ($cipher === false || $iv === false || $encKey === false || $keyIv === false) return false;
    $fileKey = openssl_decrypt($encKey, 'AES-256-CBC', MASTER_KEY, OPENSSL_RAW_DATA, $keyIv);
    if ($fileKey === false) return false;
    return openssl_decrypt($cipher, 'AES-256-CBC', $fileKey, OPENSSL_RAW_DATA, $iv);
}