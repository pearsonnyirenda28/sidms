<?php
function sendEmail(string $to, string $subject, string $body): bool {
    $from = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@localhost';
    $headers = "From: " . APP_NAME . " <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}