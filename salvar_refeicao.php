<?php
// /salvar_refeicao.php

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
$tipo_refeicao = $_POST['tipo_refeicao'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');
$calorias = !empty($_POST['calorias']) ? (int)$_POST['calorias'] : null;
$data_refeicao = date('Y-m-d'); // Salva sempre com a data atual

if (empty($tipo_refeicao) || empty($descricao)) {
    http_response_code(400);
    $response['message'] = 'O tipo e a descrição da refeição são obrigatórios.';
    echo json_encode($response);
    exit();
}

try {
    $sql = "INSERT INTO registros_alimentacao (id_usuario, data_refeicao, tipo_refeicao, descricao, calorias) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $data_refeicao, $tipo_refeicao, $descricao, $calorias]);
    
    $newRecordId = $pdo->lastInsertId();

    $response['success'] = true;
    $response['message'] = 'Refeição registrada com sucesso!';
    $response['refeicao'] = [
        'id' => $newRecordId,
        'descricao' => $descricao,
        'calorias' => $calorias,
        'tipo_refeicao' => $tipo_refeicao
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao salvar a refeição.';
    echo json_encode($response);
}
?>