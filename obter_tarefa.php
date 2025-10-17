<?php
session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

try {
    $id = (int)($_GET['id'] ?? 0);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, descricao, prioridade, data_limite, tempo_estimado, status
        FROM tarefas 
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$id, $userId]);
    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tarefa) {
        echo json_encode(['success' => true, 'tarefa' => $tarefa]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada']);
    }
} catch (PDOException $e) {
    error_log('Erro em obter_tarefa.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar']);
}
?>
