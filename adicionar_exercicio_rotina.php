<?php
// /adicionar_exercicio_rotina.php

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
$id_rotina_dia = $_POST['id_rotina_dia'] ?? 0;
$nome_exercicio = trim($_POST['nome_exercicio'] ?? '');
$series = $_POST['series_sugeridas'] ?? null;
$repeticoes = $_POST['repeticoes_sugeridas'] ?? null;

if (empty($id_rotina_dia) || empty($nome_exercicio)) {
    http_response_code(400);
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Verifica se o exercício já existe no "dicionário" do usuário.
    $stmt_check = $pdo->prepare("SELECT id FROM exercicios WHERE id_usuario = ? AND nome_exercicio = ?");
    $stmt_check->execute([$userId, $nome_exercicio]);
    $exercicioId = $stmt_check->fetchColumn();

    // 2. Se não existir, cria o exercício.
    if (!$exercicioId) {
        $stmt_create = $pdo->prepare("INSERT INTO exercicios (id_usuario, nome_exercicio) VALUES (?, ?)");
        $stmt_create->execute([$userId, $nome_exercicio]);
        $exercicioId = $pdo->lastInsertId();
    }

    // 3. Insere o exercício na rotina daquele dia.
    $stmt_insert = $pdo->prepare("INSERT INTO rotina_exercicios (id_rotina_dia, id_exercicio, series_sugeridas, repeticoes_sugeridas) VALUES (?, ?, ?, ?)");
    $stmt_insert->execute([$id_rotina_dia, $exercicioId, $series, $repeticoes]);
    $newRotinaExercicioId = $pdo->lastInsertId();

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Exercício adicionado à rotina!';
    $response['exercicio'] = [
        'id' => $newRotinaExercicioId,
        'nome_exercicio' => $nome_exercicio,
        'series_sugeridas' => $series,
        'repeticoes_sugeridas' => $repeticoes
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>