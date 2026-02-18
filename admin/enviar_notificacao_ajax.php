<?php
// /admin/enviar_notificacao_ajax.php (100% Completo)

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro.'];

// Segurança: Apenas um admin logado pode executar esta ação
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}


// Inclui suas chaves de API
require_once '../includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$titulo = $input['titulo'] ?? '';
$mensagem = $input['mensagem'] ?? '';
// Define uma URL padrão se nenhuma for enviada
$url = !empty($input['url']) ? $input['url'] : 'https://gold-quail-250128.hostingersite.com/seu_projeto/dashboard.php';

if (empty($titulo) || empty($mensagem)) {
    http_response_code(400);
    $response['message'] = 'Título e mensagem são obrigatórios.';
    echo json_encode($response);
    exit();
}

// Garante que as chaves da OneSignal estão definidas
if (!defined('ONESIGNAL_APP_ID') || !defined('ONESIGNAL_REST_API_KEY')) {
    http_response_code(500);
    $response['message'] = 'As chaves da API da OneSignal não estão configuradas no servidor.';
    echo json_encode($response);
    exit();
}

$app_id = ONESIGNAL_APP_ID;
$rest_api_key = ONESIGNAL_REST_API_KEY;

$fields = [
    'app_id' => $app_id,
    'included_segments' => ['Subscribed Users'], // Envia para todos os usuários inscritos
    'headings' => ['en' => $titulo, 'pt' => $titulo],
    'contents' => ['en' => $mensagem, 'pt' => $mensagem],
    'web_url' => $url,
    'chrome_web_icon' => 'https://gold-quail-250128.hostingersite.com/seu_projeto/images/icon-192x192.png'
];

$fields_json = json_encode($fields);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json; charset=utf-8',
    'Authorization: Basic ' . $rest_api_key
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Necessário em alguns ambientes de hospedagem

$api_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response_data = json_decode($api_response, true);

if ($http_code >= 200 && $http_code < 300) {
    $response['success'] = true;
    $response['message'] = 'Notificação enviada para a fila da OneSignal com sucesso!';
    $response['api_response'] = $response_data;
} else {
    http_response_code($http_code);
    $response['message'] = 'Falha ao enviar notificação. Resposta da API: ' . ($response_data['errors'][0] ?? 'Erro desconhecido');
    $response['api_response'] = $response_data;
}

echo json_encode($response);
?>