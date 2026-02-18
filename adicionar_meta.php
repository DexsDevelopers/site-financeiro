<?php
// /adicionar_meta.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false];
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(json_encode($response)); }
require_once 'includes/db_connect.php';
$userId = $_SESSION['user_id'];
$nome_item = trim($_POST['nome_item'] ?? '');
$valor_total = $_POST['valor_total'] ?? 0;
if (empty($nome_item) || !is_numeric($valor_total) || $valor_total <= 0) { http_response_code(400); exit(json_encode($response)); }
try {
    $sql = "INSERT INTO compras_futuras (id_usuario, nome_item, valor_total) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $nome_item, $valor_total]);
    $response['success'] = true;
    $response['message'] = 'Meta criada com sucesso!';
    echo json_encode($response);
} catch (PDOException $e) { http_response_code(500); echo json_encode($response); }
?>