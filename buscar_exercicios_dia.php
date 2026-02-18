<?php
// /buscar_exercicios_dia.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit(json_encode(['success' => false, 'message' => 'Acesso negado.'])); }
require_once 'includes/db_connect.php';
$diaId = $_GET['id_dia'] ?? 0;
$userId = $_SESSION['user_id'];
if (empty($diaId)) { http_response_code(400); exit(json_encode(['success' => false, 'message' => 'ID do dia não fornecido.'])); }
try {
    $sql = "SELECT re.id, e.nome_exercicio, re.series_sugeridas, re.repeticoes_sugeridas FROM rotina_exercicios re JOIN exercicios e ON re.id_exercicio = e.id JOIN rotina_dias rd ON re.id_rotina_dia = rd.id JOIN rotinas r ON rd.id_rotina = r.id WHERE re.id_rotina_dia = ? AND r.id_usuario = ? ORDER BY re.ordem ASC, re.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$diaId, $userId]);
    $exercicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'exercicios' => $exercicios]);
} catch (PDOException $e) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']); }
?>