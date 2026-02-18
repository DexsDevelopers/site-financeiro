<?php
// /atualizar_meta_valor.php
session_start();
header('Content-Type: application/json');
$response = ['success' => false];
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(json_encode($response)); }
require_once 'includes/db_connect.php';
$userId = $_SESSION['user_id'];
$metaId = $_POST['meta_id'] ?? 0;
$valor_adicionado = $_POST['valor_adicionado'] ?? 0;
if (empty($metaId) || !is_numeric($valor_adicionado) || $valor_adicionado <= 0) { http_response_code(400); exit(json_encode($response)); }
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE compras_futuras SET valor_poupado = valor_poupado + ? WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$valor_adicionado, $metaId, $userId]);
    $stmt_select = $pdo->prepare("SELECT valor_poupado, valor_total FROM compras_futuras WHERE id = ?");
    $stmt_select->execute([$metaId]);
    $meta = $stmt_select->fetch(PDO::FETCH_ASSOC);
    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'Valor adicionado com sucesso!';
    $response['meta'] = ['id' => $metaId, 'valor_poupado' => $meta['valor_poupado'], 'valor_total' => $meta['valor_total']];
    echo json_encode($response);
} catch (PDOException $e) { $pdo->rollBack(); http_response_code(500); echo json_encode($response); }
?>