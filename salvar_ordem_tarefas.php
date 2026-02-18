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
$tarefas = $data['tarefas'] ?? [];

if (empty($tarefas)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

try {
    // Adicionar coluna ordem se não existir
    $pdo->exec("ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS ordem INT DEFAULT 0");
    
    // Salvar ordem de cada tarefa
    $stmt = $pdo->prepare("UPDATE tarefas SET ordem = ? WHERE id = ? AND id_usuario = ?");
    
    foreach ($tarefas as $tarefa) {
        $stmt->execute([
            $tarefa['ordem'],
            (int)$tarefa['id'],
            $_SESSION['user_id']
        ]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
