<?php
// google_oauth_callback.php - Callback OAuth do Google

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/google_integration_manager.php';

$response = ['success' => false, 'message' => 'Erro desconhecido'];

try {
    if (!isset($_GET['code']) || !isset($_GET['state'])) {
        throw new Exception('Parâmetros OAuth ausentes');
    }
    
    $code = $_GET['code'];
    $state = $_GET['state'];
    
    $manager = new GoogleIntegrationManager($pdo);
    $manager->exchangeCodeForTokens($code, $state);
    
    $response['success'] = true;
    $response['message'] = 'Conta Google conectada com sucesso!';
    
    // Redirecionar para página de integrações
    header('Location: integracoes_google.php?success=1');
    exit();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Erro OAuth Google: " . $e->getMessage());
    
    header('Location: integracoes_google.php?error=' . urlencode($e->getMessage()));
    exit();
}

