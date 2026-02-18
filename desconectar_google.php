<?php
// desconectar_google.php - Desconectar conta Google

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

try {
    $manager = new GoogleIntegrationManager($pdo);
    $manager->disconnect($userId);
    
    $response['success'] = true;
    $response['message'] = 'Conta Google desconectada com sucesso!';
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
?>

