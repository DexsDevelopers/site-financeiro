<?php
// /excluir_subtarefa.php (Versão Moderna e Segura)

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
$userId = $_SESSION['user_id'];

if (empty($subtarefaId) || !is_numeric($subtarefaId)) {
    http_response_code(400);
    $response['message'] = 'ID da subtarefa inválido.';
    echo json_encode($response);
    exit();
}

try {
    // Segurança: Verifica se a subtarefa pertence a uma tarefa do usuário logado
    $sql = "DELETE s FROM subtarefas s
            JOIN tarefas t ON s.id_tarefa_principal = t.id
            WHERE s.id = ? AND t.id_usuario = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subtarefaId, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Subtarefa excluída com sucesso.';
    } else {
        http_response_code(404);
        $response['message'] = 'Subtarefa não encontrada ou você não tem permissão para excluí-la.';
    }
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao excluir a subtarefa.';
    echo json_encode($response);
}
?>