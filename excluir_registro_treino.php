<?php
// /excluir_registro_treino.php

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$registroId = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($registroId) || !is_numeric($registroId)) {
    http_response_code(400);
    $response['message'] = 'ID do registro inválido.';
    echo json_encode($response);
    exit();
}

try {
    // Segurança: Garante que o usuário só pode apagar seus próprios registros
    $stmt = $pdo->prepare("DELETE FROM registros_treino WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$registroId, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Registro de treino excluído com sucesso!';
    } else {
        http_response_code(404);
        $response['message'] = 'Registro não encontrado ou você не tem permissão para excluí-lo.';
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao excluir o registro.';
    echo json_encode($response);
}
?>