<?php
$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"model":"deepseek-coder","messages":[{"role":"system","content":"Test"},{"role":"user","content":"Hello"}],"temperature":0.7,"max_tokens":2000}');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Authorization: Bearer sk-e8ca9e37845441468076295c174512e4'
));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
$response = curl_exec($ch);
if ($response === false) {
    echo "Error: " . curl_error($ch) . PHP_EOL;
} else {
    echo $response . PHP_EOL;
}
curl_close($ch);
