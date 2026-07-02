<?php
function sendWebPush(string $endpoint, string $userPublicKey, string $userAuthToken, string $payload, string $vapidPublicKey, string $vapidPrivateKey): bool {
    $userPublicKey = base64url_decode($userPublicKey);
    $userAuthToken = base64url_decode($userAuthToken);
    $privateKey    = base64url_decode($vapidPrivateKey);

    $header = ['typ' => 'JWT', 'alg' => 'ES256'];
    $jwtPayload = [
        'aud' => parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST),
        'exp' => time() + 12 * 3600,
        'sub' => 'mailto:' . (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@localhost')
    ];

    $jwt = signJWT($header, $jwtPayload, $privateKey);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/plain',
            'Authorization: WebPush ' . $jwt,
            'TTL: 86400'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 201;
}

function signJWT(array $header, array $payload, string $privateKey): string {
    $headerEnc = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $payloadEnc = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $data = $headerEnc . '.' . $payloadEnc;
    $signature = '';
    openssl_sign($data, $signature, $privateKey, 'SHA256');
    $signatureEnc = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    return $data . '.' . $signatureEnc;
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}