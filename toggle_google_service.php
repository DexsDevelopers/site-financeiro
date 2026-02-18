<?php
// toggle_google_service.php - Habilitar/desabilitar serviço Google

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro desconhecido'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/google_integration_manager.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$service = $input['service'] ?? '';
$enabled = isset($input['enabled']) ? (bool)$input['enabled'] : false;

if (empty($service)) {
    http_response_code(400);
    $response['message'] = 'Serviço não especificado.';
    echo json_encode($response);
    exit();
}

try {
    $manager = new GoogleIntegrationManager($pdo);
    
    if (!$manager->isConnected($userId)) {
        throw new Exception('Conta Google não conectada. Conecte sua conta primeiro.');
    }
    
    $manager->setServiceEnabled($userId, $service, $enabled);
    
    $serviceNames = [
        'calendar' => 'Google Calendar',
        'drive' => 'Google Drive',
        'tasks' => 'Google Tasks',
        'gmail' => 'Gmail',
        'sheets' => 'Google Sheets'
    ];
    
    $serviceName = $serviceNames[$service] ?? $service;
    
    $response['success'] = true;
    $response['message'] = $serviceName . ' ' . ($enabled ? 'ativado' : 'desativado') . ' com sucesso!';
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>

