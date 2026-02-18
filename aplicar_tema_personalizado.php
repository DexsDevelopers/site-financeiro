<?php
// aplicar_tema_personalizado.php

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

// Buscar tema
try {
    $stmt = $pdo->prepare("SELECT * FROM temas_personalizados WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$tema_id, $userId]);
    $tema = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tema) {
        echo json_encode(['success' => false, 'message' => 'Tema não encontrado']);
        exit;
    }
    
    $cores = json_decode($tema['cores'], true);
    
    // Salvar tema ativo na sessão
    $_SESSION['tema_ativo'] = 'personalizado_' . $tema_id;
    $_SESSION['cores_tema'] = $cores;
    
    echo json_encode(['success' => true, 'message' => 'Tema aplicado com sucesso!', 'cores' => $cores]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao aplicar tema: ' . $e->getMessage()]);
}
?>
