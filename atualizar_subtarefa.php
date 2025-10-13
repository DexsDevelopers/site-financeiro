<?php
// /atualizar_subtarefa.php (100% Completo)

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro inesperado.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$subtarefaId = $input['id'] ?? 0;
$novaDescricao = trim($input['descricao'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($subtarefaId) || empty($novaDescricao)) {
    http_response_code(400);
    $response['message'] = 'Dados inválidos. A nova descrição não pode ser vazia.';
    echo json_encode($response);
    exit();
}

try {
    // Segurança: Verifica se a subtarefa pertence a uma tarefa do usuário logado
    $sql = "UPDATE subtarefas s
            JOIN tarefas t ON s.id_tarefa_principal = t.id
            SET s.descricao = ?
            WHERE s.id = ? AND t.id_usuario = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$novaDescricao, $subtarefaId, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Subtarefa atualizada com sucesso.';
    } else {
        http_response_code(404);
        $response['message'] = 'Subtarefa não encontrada ou você não tem permissão para editá-la.';
    }
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar a subtarefa.';
    echo json_encode($response);
}
?>