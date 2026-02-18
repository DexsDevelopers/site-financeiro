<?php
// /editar_recorrente.php

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
$id_recorrente = $_POST['id'] ?? 0;
$id_categoria = $_POST['id_categoria'] ?? null;
$descricao = trim($_POST['descricao'] ?? '');
$valor = $_POST['valor'] ?? null;
$dia_execucao = $_POST['dia_execucao'] ?? null;

if (empty($id_recorrente) || empty($id_categoria) || empty($descricao) || !is_numeric($valor) || $valor <= 0 || !is_numeric($dia_execucao) || $dia_execucao < 1 || $dia_execucao > 31) {
    http_response_code(400);
    $response['message'] = 'Todos os campos são obrigatórios e os valores devem ser válidos.';
    echo json_encode($response);
    exit();
}

try {
    // Segurança: Verifica se a categoria selecionada pertence ao usuário
    $stmt_cat_check = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt_cat_check->execute([$id_categoria, $userId]);
    if (!$stmt_cat_check->fetch()) {
        http_response_code(403);
        $response['message'] = 'A categoria selecionada é inválida ou não pertence a você.';
        echo json_encode($response);
        exit();
    }

    // Atualiza a transação recorrente no banco de dados
    $sql = "UPDATE transacoes_recorrentes 
            SET id_categoria = ?, descricao = ?, valor = ?, dia_execucao = ?
            WHERE id = ? AND id_usuario = ?";
    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute([$id_categoria, $descricao, $valor, $dia_execucao, $id_recorrente, $userId]);

    if ($stmt_update->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Transação recorrente atualizada com sucesso!';
    } else {
        // Se nenhuma linha foi afetada, pode ser que o usuário salvou sem alterar nada.
        // Consideramos um sucesso para a experiência do usuário.
        $response['success'] = true;
        $response['message'] = 'Nenhuma alteração detectada.';
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar a recorrência.';
    echo json_encode($response);
}
?>