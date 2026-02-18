<?php
// /salvar_registro_treino.php

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
$nome_exercicio = trim($_POST['exercicio'] ?? '');
$series = $_POST['series'] ?? null;
$repeticoes = $_POST['repeticoes'] ?? null;
$carga = !empty($_POST['carga']) ? (float)$_POST['carga'] : null;
$observacoes = trim($_POST['observacoes'] ?? '');

if (empty($nome_exercicio)) {
    http_response_code(400);
    $response['message'] = 'O nome do exercício é obrigatório.';
    echo json_encode($response);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Verifica se o exercício já existe no "dicionário" do usuário.
    $stmt_check = $pdo->prepare("SELECT id FROM exercicios WHERE id_usuario = ? AND nome_exercicio = ?");
    $stmt_check->execute([$userId, $nome_exercicio]);
    $exercicioId = $stmt_check->fetchColumn();

    // 2. Se não existir, cria o exercício no dicionário.
    if (!$exercicioId) {
        $stmt_create_exercicio = $pdo->prepare("INSERT INTO exercicios (id_usuario, nome_exercicio) VALUES (?, ?)");
        $stmt_create_exercicio->execute([$userId, $nome_exercicio]);
        $exercicioId = $pdo->lastInsertId();
    }

    // 3. Insere o registro do treino na tabela principal.
    $sql_insert = "INSERT INTO registros_treino (id_exercicio, id_usuario, data_treino, series, repeticoes, carga, observacoes) 
                   VALUES (?, ?, CURDATE(), ?, ?, ?, ?)";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([$exercicioId, $userId, $series, $repeticoes, $carga, $observacoes]);
    
    $newRecordId = $pdo->lastInsertId();

    $pdo->commit();

    // 4. Resposta de Sucesso
    $response['success'] = true;
    $response['message'] = 'Exercício adicionado ao treino com sucesso!';
    $response['registro'] = [
        'id' => $newRecordId,
        'nome_exercicio' => $nome_exercicio,
        'series' => $series,
        'repeticoes' => $repeticoes,
        'carga' => $carga,
        'observacoes' => $observacoes
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao salvar o registro.';
    echo json_encode($response);
}
?>