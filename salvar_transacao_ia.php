<?php
// /salvar_transacao_ia.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro desconhecido.'];

if (!isset($_SESSION['user_id'])) { http_response_code(403); $response['message'] = 'Acesso negado.'; echo json_encode($response); exit(); }
require_once 'includes/db_connect.php';

$id_usuario = $_SESSION['user_id'];
$descricao = $_POST['descricao'] ?? '';
$valor = $_POST['valor'] ?? '';
$categoria_nome = $_POST['categoria_nome'] ?? '';
$data_transacao = $_POST['data_transacao'] ?? '';

if (empty($descricao) || !is_numeric($valor) || empty($data_transacao)) {
    http_response_code(400);
    $response['message'] = 'Dados insuficientes fornecidos pela IA.';
    echo json_encode($response);
    exit();
}

if (empty($categoria_nome)) {
    http_response_code(400);
    $response['message'] = 'A categoria não foi especificada pela IA. Por favor, tente novamente incluindo a categoria na sua frase (ex: "gastei 10 reais em Alimentação").';
    echo json_encode($response);
    exit();
}

try {
    // Busca o ID e o tipo da categoria pelo nome
    $stmt_cat = $pdo->prepare("SELECT id, tipo FROM categorias WHERE nome = ? AND id_usuario = ?");
    $stmt_cat->execute([$categoria_nome, $id_usuario]);
    $categoria = $stmt_cat->fetch();

    if (!$categoria) {
        http_response_code(400);
        $response['message'] = "A categoria '$categoria_nome' não foi encontrada. Por favor, crie-a primeiro.";
        echo json_encode($response);
        exit();
    }
    
    $id_categoria = $categoria['id'];
    $tipo = $categoria['tipo'];

    $sql = "INSERT INTO transacoes (id_usuario, id_categoria, descricao, valor, tipo, data_transacao) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario, $id_categoria, $descricao, $valor, $tipo, $data_transacao]);

    $response['success'] = true;
    $response['message'] = 'Lançamento salvo com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>