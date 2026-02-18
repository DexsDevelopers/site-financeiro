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
    // Aceitar JSON ou form-data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Aceitar tanto 'tarefa_id' quanto 'id_tarefa_principal' (compatibilidade)
    $tarefaId = $input['tarefa_id'] ?? $input['id_tarefa_principal'] ?? null;
    $descricao = trim($input['descricao'] ?? '');
    
    // Validações
    if (empty($tarefaId)) {
        echo json_encode(['success' => false, 'message' => 'ID da tarefa é obrigatório']);
        exit;
    }
    
    if (empty($descricao)) {
        echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória']);
        exit;
    }
    
    // Verificar se a tarefa pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM tarefas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$tarefaId, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada ou sem permissão']);
        exit;
    }
    
    // Inserir subtarefa
    $stmt = $pdo->prepare("
        INSERT INTO subtarefas (id_tarefa_principal, descricao, status, data_criacao) 
        VALUES (?, ?, 'pendente', NOW())
    ");
    
    $stmt->execute([$tarefaId, $descricao]);
    
    $subtarefaId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Subtarefa adicionada com sucesso',
        'subtarefa' => [
            'id' => $subtarefaId,
            'descricao' => $descricao,
            'status' => 'pendente'
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao adicionar subtarefa: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar subtarefa']);
}

