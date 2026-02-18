<?php
// /atualizar_status_curso.php (100% Completo)
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
$cursoId = $input['id'] ?? 0;
$novoStatus = $input['status'] ?? '';
$ordemIds = $input['ordem'] ?? [];
$userId = $_SESSION['user_id'];

if (empty($cursoId) || !in_array($novoStatus, ['pendente', 'assistindo', 'concluido'])) {
    http_response_code(400);
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Atualiza o status do curso que foi movido
    $stmt_update = $pdo->prepare("UPDATE cursos SET status = ? WHERE id = ? AND id_usuario = ?");
    $stmt_update->execute([$novoStatus, $cursoId, $userId]);

    // 2. Atualiza a ordem de todos os cursos na nova coluna
    $sql_ordem = "UPDATE cursos SET ordem = ? WHERE id = ? AND id_usuario = ?";
    $stmt_ordem = $pdo->prepare($sql_ordem);
    foreach ($ordemIds as $index => $id) {
        $stmt_ordem->execute([$index, $id, $userId]);
    }

    $pdo->commit();
    
    $response['success'] = true;
    $response['message'] = 'Status do curso atualizado com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>