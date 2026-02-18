<?php
// /adicionar_compra_futura.php (Versão com Data Alvo)

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
$nome_item = trim($_POST['nome_item'] ?? '');
$valor_estimado = !empty($_POST['valor_estimado']) ? (float)$_POST['valor_estimado'] : null;
$link_referencia = trim($_POST['link_referencia'] ?? '');
$data_alvo = !empty($_POST['data_alvo']) ? $_POST['data_alvo'] : null;

if (empty($nome_item)) {
    http_response_code(400);
    $response['message'] = 'O nome do item é obrigatório.';
    echo json_encode($response);
    exit();
}

try {
    $sql = "INSERT INTO compras_futuras (id_usuario, nome_item, valor_estimado, link_referencia, data_alvo) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $nome_item, $valor_estimado, $link_referencia, $data_alvo]);
    
    $response['success'] = true;
    $response['message'] = 'Compra futura adicionada com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao salvar a compra.';
    echo json_encode($response);
}
?>