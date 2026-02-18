<?php
/**
 * Script de teste para verificar status da API Gemini
 * Use este script para diagnosticar problemas com a API
 */

require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Verificar se a chave da API está definida
if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
    echo json_encode([
        'success' => false,
        'error' => 'GEMINI_API_KEY não está definida'
    ]);
    exit();
}

// Teste simples da API
$testPrompt = "Responda apenas: OK";
$gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . GEMINI_API_KEY;
$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $testPrompt]
            ]
        ]
    ]
];

$ch = curl_init($gemini_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response_string = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_info = curl_getinfo($ch);
curl_close($ch);

$result = [
    'success' => $http_code === 200,
    'http_code' => $http_code,
    'curl_error' => $curl_error ?: null,
    'response_time' => $curl_info['total_time'] ?? null,
    'api_key_preview' => substr(GEMINI_API_KEY, 0, 10) . '...',
    'timestamp' => date('Y-m-d H:i:s')
];

if ($http_code === 200) {
    $response_data = json_decode($response_string, true);
    $result['api_response'] = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? 'Resposta vazia';
    $result['message'] = 'API funcionando normalmente';
} else {
    $response_data = json_decode($response_string, true);
    $result['error_message'] = $response_data['error']['message'] ?? 'Erro desconhecido';
    $result['error_details'] = $response_data['error'] ?? null;
    $result['response_preview'] = substr($response_string, 0, 500);
    
    // Analisar tipo de erro
    if ($http_code === 429) {
        $errorMsg = $result['error_message'];
        $isQuota = (
            stripos($errorMsg, 'quota') !== false || 
            stripos($errorMsg, 'limit: 0') !== false ||
            stripos($errorMsg, 'free_tier') !== false
        );
        $result['error_type'] = $isQuota ? 'quota_exceeded' : 'rate_limit';
        $result['message'] = $isQuota 
            ? 'Cota da API excedida' 
            : 'Rate limit temporário (muitas requisições em pouco tempo)';
    } elseif ($http_code === 401) {
        $result['error_type'] = 'invalid_api_key';
        $result['message'] = 'Chave API inválida ou expirada';
    } elseif ($http_code === 403) {
        $result['error_type'] = 'access_denied';
        $result['message'] = 'Acesso negado. Verifique as permissões da API';
    } else {
        $result['error_type'] = 'unknown';
        $result['message'] = "Erro HTTP $http_code";
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>

