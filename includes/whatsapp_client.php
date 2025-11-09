<?php
// includes/whatsapp_client.php
// Cliente simples para enviar mensagens ao bot local (HTTP).

function wpp_get_config(): array {
    // Configure aqui ou via variáveis de ambiente/servidor
    $base = getenv('WHATSAPP_API_URL')
        ?: ($_ENV['WHATSAPP_API_URL'] ?? null)
        ?: ($_SERVER['WHATSAPP_API_URL'] ?? 'http://localhost:3000');

    $token = getenv('WHATSAPP_API_TOKEN')
        ?: ($_ENV['WHATSAPP_API_TOKEN'] ?? null)
        ?: ($_SERVER['WHATSAPP_API_TOKEN'] ?? 'troque-este-token');
    return ['base' => rtrim($base, '/'), 'token' => $token];
}

function wpp_send_message(string $toE164, string $text): array {
    $cfg = wpp_get_config();
    $url = $cfg['base'] . '/send';
    $payload = json_encode(['to' => $toE164, 'text' => $text], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-token: ' . $cfg['token']
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => $err, 'status' => $status];
    $data = json_decode($resp, true);
    if (!$data) return ['ok' => false, 'error' => 'invalid_json', 'status' => $status];
    return $data;
}
?>


