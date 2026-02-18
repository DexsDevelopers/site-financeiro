<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, cor 
        FROM tarefas_categorias 
        WHERE id_usuario = ? 
        ORDER BY nome
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'categorias' => $categorias
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar categorias: ' . $e->getMessage()
    ]);
}
?>
