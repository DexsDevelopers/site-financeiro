<?php
// excluir_tema_personalizado.php

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
$tema_id = (int) ($input['tema_id'] ?? 0);

if ($tema_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do tema inválido']);
    exit;
}

// Verificar se o tema pertence ao usuário
try {
    $stmt_check = $pdo->prepare("SELECT id FROM temas_personalizados WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$tema_id, $userId]);
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Tema não encontrado']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar tema']);
    exit;
}

// Excluir tema
try {
    $stmt = $pdo->prepare("DELETE FROM temas_personalizados WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$tema_id, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Tema excluído com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir tema: ' . $e->getMessage()]);
}
?>
