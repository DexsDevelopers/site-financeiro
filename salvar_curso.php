<?php
// /salvar_curso.php (100% Completo)
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

$userId = $_SESSION['user_id'];
$nome_curso = trim($_POST['nome_curso'] ?? '');
$link_curso = trim($_POST['link_curso'] ?? '');

if (empty($nome_curso)) {
    http_response_code(400);
    $response['message'] = 'O nome do curso é obrigatório.';
    echo json_encode($response);
    exit();
}

try {
    $sql = "INSERT INTO cursos (id_usuario, nome_curso, link_curso, status) VALUES (?, ?, ?, 'pendente')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $nome_curso, $link_curso]);
    
    $newCourseId = $pdo->lastInsertId();

    $response['success'] = true;
    $response['message'] = 'Curso adicionado com sucesso!';
    $response['curso'] = [
        'id' => $newCourseId,
        'nome_curso' => $nome_curso,
        'link_curso' => $link_curso
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao salvar o curso.';
    echo json_encode($response);
}
?>