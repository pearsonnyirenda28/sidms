<?php
function totpGenerateSecret(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 16; $i++) $secret .= $chars[random_int(0, 31)];
    return $secret;
}
function totpBase32Decode(string $s): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(rtrim($s, '='));
    $bits = '';
    foreach (str_split($s) as $c) {
        $v = strpos($chars, $c);
        if ($v === false) continue;
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function totpGenerate(string $secret, int $t = 0): string {
    $key = totpBase32Decode($secret);
    $time = intdiv(time() + $t * 30, 30);
    $msg = pack('N*', 0) . pack('N*', $time);
    $hash = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $otp = ((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset+1]) & 0xFF) << 16) | ((ord($hash[$offset+2]) & 0xFF) << 8) | (ord($hash[$offset+3]) & 0xFF);
    return str_pad($otp % 1000000, 6, '0', STR_PAD_LEFT);
}
function totpVerify(string $secret, string $code): bool {
    foreach ([-1, 0, 1] as $t) if (hash_equals(totpGenerate($secret, $t), $code)) return true;
    return false;
}
function totpQRUrl(string $username, string $secret): string {
    $label = rawurlencode(APP_NAME . ':' . $username);
    $params = http_build_query(['secret' => $secret, 'issuer' => APP_NAME, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30]);
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode("otpauth://totp/{$label}?{$params}");
}