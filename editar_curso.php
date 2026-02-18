<?php
// /editar_curso.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro inesperado.'];

if (!isset($_SESSION['user_id'])) { http_response_code(403); $response['message'] = 'Acesso negado.'; echo json_encode($response); exit(); }
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$cursoId = $_POST['id'] ?? 0;
$nome_curso = trim($_POST['nome_curso'] ?? '');
$link_curso = trim($_POST['link_curso'] ?? '');

if (empty($cursoId) || empty($nome_curso)) {
    http_response_code(400);
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE cursos SET nome_curso = ?, link_curso = ? WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$nome_curso, $link_curso, $cursoId, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Curso atualizado com sucesso!';
    } else {
        http_response_code(404);
        $response['message'] = 'Curso não encontrado ou nenhuma alteração foi feita.';
    }
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>