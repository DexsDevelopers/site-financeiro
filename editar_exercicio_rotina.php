<?php
// /editar_exercicio_rotina.php

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
$id_rotina_exercicio = $input['id'] ?? 0;
$series = $input['series_sugeridas'] ?? null;
$repeticoes = $input['repeticoes_sugeridas'] ?? null;
$userId = $_SESSION['user_id'];

if (empty($id_rotina_exercicio)) {
    http_response_code(400);
    $response['message'] = 'ID do exercício da rotina inválido.';
    echo json_encode($response);
    exit();
}

try {
    // Segurança: Garante que o usuário só pode editar seus próprios exercícios da rotina
    $sql = "UPDATE rotina_exercicios re
            JOIN rotina_dias rd ON re.id_rotina_dia = rd.id
            JOIN rotinas r ON rd.id_rotina = r.id
            SET re.series_sugeridas = ?, re.repeticoes_sugeridas = ?
            WHERE re.id = ? AND r.id_usuario = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$series, $repeticoes, $id_rotina_exercicio, $userId]);

    $response['success'] = true;
    $response['message'] = 'Exercício atualizado com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar o exercício.';
    echo json_encode($response);
}
?>