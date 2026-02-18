<?php
// /editar_meta.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false];
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(json_encode($response)); }
require_once 'includes/db_connect.php';
$userId = $_SESSION['user_id'];
$metaId = $_POST['id'] ?? 0;
$nome_item = trim($_POST['nome_item'] ?? '');
$valor_total = $_POST['valor_total'] ?? 0;
if (empty($metaId) || empty($nome_item) || !is_numeric($valor_total) || $valor_total <= 0) { http_response_code(400); exit(json_encode($response)); }
try {
    $stmt = $pdo->prepare("UPDATE compras_futuras SET nome_item = ?, valor_total = ? WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$nome_item, $valor_total, $metaId, $userId]);
    $response['success'] = true;
    $response['message'] = 'Meta atualizada!';
    echo json_encode($response);
} catch (PDOException $e) { http_response_code(500); echo json_encode($response); }
?>