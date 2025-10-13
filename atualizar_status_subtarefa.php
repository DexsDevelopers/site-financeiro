<?php
// atualizar_status_subtarefa.php - Atualizar status de subtarefa
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$subtaskId = (int)($input['id'] ?? 0);
$status = $input['status'] ?? '';

if ($subtaskId <= 0 || !in_array($status, ['pendente', 'concluida'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    // Verificar se a subtarefa pertence ao usuário através da tarefa principal
    $stmt_check = $pdo->prepare("
        SELECT s.id 
        FROM subtarefas s 
        JOIN tarefas t ON s.id_tarefa_principal = t.id 
        WHERE s.id = ? AND t.id_usuario = ?
    ");
    $stmt_check->execute([$subtaskId, $userId]);
    
    if (!$stmt_check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Subtarefa não encontrada']);
        exit;
    }
    
    // Atualizar status da subtarefa
    $stmt_update = $pdo->prepare("UPDATE subtarefas SET status = ? WHERE id = ?");
    $result = $stmt_update->execute([$status, $subtaskId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>