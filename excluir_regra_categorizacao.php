<?php
// excluir_regra_categorizacao.php

session_start();
require_once 'includes/db_connect.php';

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
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da regra inválido']);
    exit;
}

// Verificar se a regra pertence ao usuário
try {
    $stmt_check = $pdo->prepare("SELECT id FROM regras_categorizacao WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$id, $userId]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Regra não encontrada']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar regra']);
    exit;
}

// Excluir regra
try {
    $stmt = $pdo->prepare("DELETE FROM regras_categorizacao WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Regra excluída com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir regra: ' . $e->getMessage()]);
}
?>
