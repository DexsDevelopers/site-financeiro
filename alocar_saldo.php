<?php
// /alocar_saldo.php

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
$alocacoes = $_POST['alocacao'] ?? [];

if (empty($alocacoes)) {
    http_response_code(400);
    $response['message'] = 'Nenhum valor para alocar foi enviado.';
    echo json_encode($response);
    exit();
}

try {
    $pdo->beginTransaction();

    $sql = "UPDATE compras_futuras SET valor_poupado = valor_poupado + ? WHERE id = ? AND id_usuario = ?";
    $stmt = $pdo->prepare($sql);

    foreach ($alocacoes as $metaId => $valor) {
        $valorFloat = (float)$valor;
        if ($valorFloat > 0) {
            $stmt->execute([$valorFloat, $metaId, $userId]);
        }
    }

    $pdo->commit();

    $response['success'] = true;
    $response['message'] = 'Saldo alocado para as metas com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao alocar o saldo.';
    echo json_encode($response);
}
?>