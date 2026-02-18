<?php
// revogar_sessao.php

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
$token = $input['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
    exit;
}

try {
    $rememberManager = new RememberMeManager($pdo);
    
    // Verificar se o token pertence ao usuário
    $stmt = $pdo->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND user_id = ?");
    $stmt->execute([$token, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Token não encontrado ou não pertence ao usuário']);
        exit;
    }
    
    // Revogar token
    if ($rememberManager->revokeToken($token)) {
        echo json_encode(['success' => true, 'message' => 'Sessão revogada com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao revogar sessão']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
}
?>
