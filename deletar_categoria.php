<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

require_once 'includes/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$categoriaId = (int)($data['id'] ?? 0);

if (!$categoriaId) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

try {
    // Verificar se categoria pertence ao usuário
    $stmt = $pdo->prepare("
        SELECT id FROM tarefas_categorias 
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$categoriaId, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false]);
        exit;
    }
    
    // Deletar categoria
    $stmt = $pdo->prepare("DELETE FROM tarefas_categorias WHERE id = ?");
    $stmt->execute([$categoriaId]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
?>
