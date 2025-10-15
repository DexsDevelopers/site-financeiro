<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Verificar autenticação
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Receber dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$rotinaId = $input['id'] ?? null;
$nome = trim($input['nome'] ?? '');
$horarioSugerido = $input['horario'] ?? null;
$descricao = trim($input['descricao'] ?? '');

if (!$rotinaId || !$nome) {
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos']);
    exit;
}

// Converter horário vazio para NULL
if (empty($horarioSugerido) || $horarioSugerido === '00:00') {
    $horarioSugerido = null;
}

try {
    // Verificar se a rotina pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$rotinaId, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Rotina não encontrada ou não pertence ao usuário']);
        exit;
    }
    
    // Atualizar a rotina
    $stmt = $pdo->prepare("
        UPDATE rotinas_fixas 
        SET nome = ?, horario_sugerido = ?, descricao = ?
        WHERE id = ? AND id_usuario = ?
    ");
    
    $stmt->execute([$nome, $horarioSugerido, $descricao, $rotinaId, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Rotina fixa atualizada com sucesso!']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>