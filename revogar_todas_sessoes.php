<?php
// revogar_todas_sessoes.php

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/remember_me_manager.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['revogar_todas']) || !$input['revogar_todas']) {
    echo json_encode(['success' => false, 'message' => 'Parâmetro inválido']);
    exit;
}

try {
    $rememberManager = new RememberMeManager($pdo);
    
    // Revogar todos os tokens do usuário
    if ($rememberManager->revokeAllUserTokens($userId)) {
        echo json_encode(['success' => true, 'message' => 'Todas as sessões foram revogadas!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao revogar sessões']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
}
?>
