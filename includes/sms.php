<?php
function sendSmsAlert(string $message): void {
    if (!SMS_ENABLED || !TWILIO_SID) return;
    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/".TWILIO_SID."/Messages.json");
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['From'=>TWILIO_FROM,'To'=>ADMIN_PHONE,'Body'=>$message]), CURLOPT_USERPWD=>TWILIO_SID.':'.TWILIO_TOKEN, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
    curl_exec($ch); curl_close($ch);
}