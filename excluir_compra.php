<?php
// /excluir_compra.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro inesperado.'];
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(json_encode(['message' => 'Acesso negado.'])); }
require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($id)) { http_response_code(400); exit(json_encode(['message' => 'ID inválido.'])); }

try {
    $stmt = $pdo->prepare("DELETE FROM compras_futuras WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $userId]);
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Compra excluída com sucesso!';
    } else {
        http_response_code(404);
        $response['message'] = 'Compra não encontrada.';
    }
    echo json_encode($response);
} catch (PDOException $e) { http_response_code(500); echo json_encode(['message' => 'Erro no banco de dados.']); }
?>