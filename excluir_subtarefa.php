<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Verificar se usuário está logado
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user']['id'];

try {
    // Aceitar JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $subtarefaId = $input['id'] ?? null;
    
    // Validações
    if (empty($subtarefaId)) {
        echo json_encode(['success' => false, 'message' => 'ID da subtarefa é obrigatório']);
        exit;
    }
    
    // Verificar se a subtarefa pertence a uma tarefa do usuário (segurança)
    $stmt = $pdo->prepare("
        SELECT s.id 
        FROM subtarefas s
        INNER JOIN tarefas t ON s.id_tarefa_principal = t.id
        WHERE s.id = ? AND t.id_usuario = ?
    ");
    $stmt->execute([$subtarefaId, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Subtarefa não encontrada ou sem permissão']);
        exit;
    }
    
    // Deletar subtarefa
    $stmt = $pdo->prepare("DELETE FROM subtarefas WHERE id = ?");
    $stmt->execute([$subtarefaId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Subtarefa excluída com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao excluir subtarefa: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir subtarefa']);
}

