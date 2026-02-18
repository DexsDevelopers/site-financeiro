<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

$userId = $_SESSION['user_id'];
$dataHoje = date('Y-m-d');

try {
    // Buscar rotinas fixas ativas com controle diário
    $stmt = $pdo->prepare("
        SELECT rf.*, 
               rcd.status as status_hoje,
               rcd.horario_execucao,
               rcd.observacoes
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY rf.ordem, rf.horario_sugerido
    ");
    $stmt->execute([$dataHoje, $userId]);
    $rotinas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'rotinas' => $rotinas
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar rotinas fixas: ' . $e->getMessage()
    ]);
}
?>
