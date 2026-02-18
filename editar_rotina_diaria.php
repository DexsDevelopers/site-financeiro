<?php
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$rotinaId = $_POST['id'] ?? null;
$nome = trim($_POST['nome'] ?? '');
$horarioSugerido = $_POST['horario_sugerido'] ?? null;
$descricao = trim($_POST['descricao'] ?? '');

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
    $stmt = $pdo->prepare("SELECT id FROM rotina_diaria WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$rotinaId, $userId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Rotina não encontrada ou não pertence ao usuário']);
        exit;
    }
    
    // Atualizar a rotina
    $stmt = $pdo->prepare("
        UPDATE rotina_diaria 
        SET nome = ?, horario_sugerido = ?, descricao = ?
        WHERE id = ? AND id_usuario = ?
    ");
    
    $stmt->execute([$nome, $horarioSugerido, $descricao, $rotinaId, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Rotina diária atualizada com sucesso!']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>