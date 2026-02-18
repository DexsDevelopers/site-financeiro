<?php
// includes/whatsapp_client.php

function wpp_get_config(): array {
    $base = getenv('WHATSAPP_API_URL')
        ?: ($_ENV['WHATSAPP_API_URL'] ?? null)
        ?: ($_SERVER['WHATSAPP_API_URL'] ?? 'http://localhost:3001'); // Porta 3001 para Site Financeiro

    $token = getenv('WHATSAPP_API_TOKEN')
        ?: ($_ENV['WHATSAPP_API_TOKEN'] ?? null)
        ?: ($_SERVER['WHATSAPP_API_TOKEN'] ?? 'site-financeiro-token-2024');

    return ['base' => rtrim($base, '/'), 'token' => $token];
}

/**
 * Normaliza número para formato E.164
 */
function wpp_normalize_number(string $num): ?string {
    // Remove tudo que não é dígito
    $num = preg_replace('/\D+/', '', $num);

    if (strlen($num) == 11) { // Ex: DDD + número
        $num = '+55' . $num;
    } elseif (strlen($num) >= 12 && substr($num, 0, 2) !== '55') {
        $num = '+' . $num;
    } elseif (substr($num, 0, 1) !== '+') {
        $num = '+' . $num;
    }

    // Valida mínimo 10 dígitos após código de país
    if (preg_match('/^\+\d{10,15}$/', $num)) {
        return $num;
    }

    return null; // Número inválido
}

/**
 * Envia mensagem para número E.164
 */
function wpp_send_message(string $to, string $text): array {
    $cfg = wpp_get_config();

    $to = wpp_normalize_number($to);
    if (!$to) {
        return ['ok' => false, 'error' => 'invalid_number'];
    }

    $url = $cfg['base'] . '/send';
    $payload = json_encode(['to' => $to, 'text' => $text], JSON_UNESCAPED_UNICODE);

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

    // Checa se o bot retornou erro de número não registrado
    if (isset($data['error']) && $data['error'] === 'number_not_registered') {
        return ['ok' => false, 'error' => 'number_not_registered', 'status' => $status];
    }

    return $data;
}

/**
 * Testa se o número está registrado no WhatsApp (via endpoint /check do bot)
 */
function wpp_test_number(string $to): array {
    $cfg = wpp_get_config();
    $to = wpp_normalize_number($to);
    if (!$to) return ['ok' => false, 'error' => 'invalid_number'];

    $url = $cfg['base'] . '/check';
    $payload = json_encode(['to' => $to], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-token: ' . $cfg['token']
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
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
