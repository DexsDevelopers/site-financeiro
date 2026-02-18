<?php
// /excluir_curso.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro inesperado.'];

if (!isset($_SESSION['user_id'])) { http_response_code(403); $response['message'] = 'Acesso negado.'; echo json_encode($response); exit(); }
require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$cursoId = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($cursoId) || !is_numeric($cursoId)) { http_response_code(400); $response['message'] = 'ID inválido.'; echo json_encode($response); exit(); }

try {
    $stmt = $pdo->prepare("DELETE FROM cursos WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$cursoId, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Curso excluído com sucesso!';
    } else {
        http_response_code(404);
        $response['message'] = 'Curso não encontrado ou você não tem permissão para excluí-lo.';
    }
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>