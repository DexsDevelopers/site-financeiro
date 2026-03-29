<?php
header('Content-Type: application/json');
require_once 'includes/db_connect.php';
require_once 'includes/push_helper.php';

session_start();

// Pegar o ID do usuário da sessão
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Endpoint específico do dispositivo (opcional) - enviado via POST JSON
$input = json_decode(file_get_contents('php://input'), true);
$endpointFiltro = $input['endpoint'] ?? null;

$title = "Ghost Pix: Teste de Push";
$body = "Sucesso! Seu dispositivo está configurado para receber notificações nativas.";
$url = "dashboard.php";

// Se um endpoint foi passado, enviar só para aquele dispositivo
if ($endpointFiltro) {
    $result = sendWebPushToEndpoint($pdo, $userId, $endpointFiltro, $title, $body, $url);
} else {
    $result = sendWebPush($pdo, $userId, $title, $body, $url);
}

if ($result && isset($result['success']) && $result['success']) {
    if ($result['sent'] > 0) {
        echo json_encode(['success' => true, 'message' => 'Notificação enviada! Verifique seu dispositivo.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhum dispositivo inscrito. Ative as notificações primeiro.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Falha ao enviar notificação. Erro de VAPID ou permissão.']);
}
