<?php
// /excluir_meta.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erro inesperado.'];

if (!isset($_SESSION['user_id'])) { /* ... código de erro ... */ }
require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$metaId = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($metaId) || !is_numeric($metaId)) { /* ... código de erro ... */ }

try {
    $stmt = $pdo->prepare("DELETE FROM metas_compra WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$metaId, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Meta excluída com sucesso!';
    } else {
        http_response_code(404);
        $response['message'] = 'Meta não encontrada ou você não tem permissão para excluí-la.';
    }
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>