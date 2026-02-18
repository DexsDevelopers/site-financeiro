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
    $descricao = trim($input['descricao'] ?? '');
    $status = $input['status'] ?? null;
    
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
    
    // Montar query de atualização dinamicamente
    $updates = [];
    $params = [];
    
    if (!empty($descricao)) {
        $updates[] = "descricao = ?";
        $params[] = $descricao;
    }
    
    if (!empty($status) && in_array($status, ['pendente', 'concluida'])) {
        $updates[] = "status = ?";
        $params[] = $status;
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
        exit;
    }
    
    $params[] = $subtarefaId;
    
    $sql = "UPDATE subtarefas SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Subtarefa atualizada com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao atualizar subtarefa: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar subtarefa']);
}

