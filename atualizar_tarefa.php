<?php
session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

require_once 'includes/db_connect.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $descricao = trim($input['descricao'] ?? '');
    $prioridade = trim($input['prioridade'] ?? 'Média');
    $data_limite = !empty($input['data_limite']) ? $input['data_limite'] : null;

    if (!$id || !$descricao) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID e descrição são obrigatórios']);
        exit;
    }

    // Validar prioridade
    if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'])) {
        $prioridade = 'Média';
    }

    $stmt = $pdo->prepare("
        UPDATE tarefas 
        SET descricao = ?, prioridade = ?, data_limite = ?
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$descricao, $prioridade, $data_limite, $id, $userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Tarefa atualizada']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada']);
    }
} catch (PDOException $e) {
    error_log('Erro em atualizar_tarefa.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
}
?>
