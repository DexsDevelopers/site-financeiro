<?php
// salvar_rotina_semanal.php - Salvar nova rotina semanal
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $nome_treino = trim($_POST['nome_treino'] ?? '');
    $dia_semana = (int)($_POST['dia_semana'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    $duracao = (int)($_POST['duracao'] ?? 60);
    $nivel = $_POST['nivel'] ?? 'iniciante';
    
    if (empty($nome_treino) || $dia_semana < 1 || $dia_semana > 7) {
        echo json_encode(['success' => false, 'message' => 'Nome do treino e dia da semana são obrigatórios']);
        exit;
    }
    
    // Verificar se já existe uma rotina ativa para este usuário
    $stmt_rotina = $pdo->prepare("SELECT id FROM rotinas WHERE id_usuario = ? AND ativo = 1");
    $stmt_rotina->execute([$userId]);
    $rotina_existente = $stmt_rotina->fetch();
    
    $rotina_id = null;
    if ($rotina_existente) {
        $rotina_id = $rotina_existente['id'];
    } else {
        // Criar nova rotina
        $stmt_nova_rotina = $pdo->prepare("INSERT INTO rotinas (id_usuario, nome_rotina, ativo, data_criacao) VALUES (?, 'Rotina Principal', 1, NOW())");
        $stmt_nova_rotina->execute([$userId]);
        $rotina_id = $pdo->lastInsertId();
    }
    
    // Verificar se já existe um dia para este dia da semana
    $stmt_dia = $pdo->prepare("SELECT id FROM rotina_dias WHERE id_rotina = ? AND dia_semana = ?");
    $stmt_dia->execute([$rotina_id, $dia_semana]);
    $dia_existente = $stmt_dia->fetch();
    
    if ($dia_existente) {
        // Atualizar dia existente
        $stmt_update = $pdo->prepare("UPDATE rotina_dias SET nome_treino = ?, descricao = ?, duracao = ?, nivel = ? WHERE id = ?");
        $result = $stmt_update->execute([$nome_treino, $descricao, $duracao, $nivel, $dia_existente['id']]);
    } else {
        // Criar novo dia
        $stmt_novo_dia = $pdo->prepare("INSERT INTO rotina_dias (id_rotina, dia_semana, nome_treino, descricao, duracao, nivel) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt_novo_dia->execute([$rotina_id, $dia_semana, $nome_treino, $descricao, $duracao, $nivel]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Treino salvo com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar treino']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>