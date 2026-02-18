<?php
// /editar_compra.php (Versão com Data Alvo)
session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro inesperado.'];
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(json_encode(['message' => 'Acesso negado.'])); }
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$id = $_POST['id'] ?? 0;
$nome_item = trim($_POST['nome_item'] ?? '');
$valor_estimado = $_POST['valor_estimado'] ?? null;
$link_referencia = trim($_POST['link_referencia'] ?? '');
$data_alvo = !empty($_POST['data_alvo']) ? $_POST['data_alvo'] : null;

if (empty($id) || empty($nome_item)) { http_response_code(400); exit(json_encode(['message' => 'Dados inválidos.'])); }

try {
    $stmt = $pdo->prepare("UPDATE compras_futuras SET nome_item = ?, valor_estimado = ?, link_referencia = ?, data_alvo = ? WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$nome_item, $valor_estimado, $link_referencia, $data_alvo, $id, $userId]);
    $response['success'] = true;
    $response['message'] = 'Compra atualizada com sucesso!';
    echo json_encode($response);
} catch (PDOException $e) { http_response_code(500); echo json_encode(['message' => 'Erro no banco de dados.']); }
?>