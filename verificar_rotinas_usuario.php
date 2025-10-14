<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['erro' => 'Usuário não logado']);
    exit;
}

try {
    // Buscar todas as rotinas do usuário
    $stmt = $pdo->prepare("
        SELECT id, nome, ativo, data_criacao 
        FROM rotinas_fixas 
        WHERE id_usuario = ? 
        ORDER BY id
    ");
    $stmt->execute([$userId]);
    $rotinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar controles de hoje
    $dataHoje = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT rcd.id_rotina_fixa, rcd.status, rcd.horario_execucao, rf.nome
        FROM rotina_controle_diario rcd
        JOIN rotinas_fixas rf ON rcd.id_rotina_fixa = rf.id
        WHERE rcd.id_usuario = ? AND rcd.data_execucao = ?
        ORDER BY rcd.id_rotina_fixa
    ");
    $stmt->execute([$userId, $dataHoje]);
    $controles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'user_id' => $userId,
        'data_hoje' => $dataHoje,
        'rotinas_existentes' => $rotinas,
        'controles_hoje' => $controles,
        'total_rotinas' => count($rotinas),
        'total_controles' => count($controles)
    ], JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    echo json_encode([
        'erro' => 'Erro no banco de dados',
        'message' => $e->getMessage()
    ]);
}
?>
