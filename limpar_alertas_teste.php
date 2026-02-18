<?php
// limpar_alertas_teste.php - Limpar alertas de teste

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Limpar todos os alertas de teste (que contêm textos específicos de teste)
    $stmt = $pdo->prepare("DELETE FROM alertas_inteligentes WHERE id_usuario = ? AND (
        titulo LIKE '%Gasto Alto Detectado%' OR
        titulo LIKE '%Padrão de Gasto Identificado%' OR
        titulo LIKE '%Meta de Economia Atingida%' OR
        titulo LIKE '%Saldo Baixo%' OR
        mensagem LIKE '%R$ 1.200,00%' OR
        mensagem LIKE '%Supermercado Extra%' OR
        mensagem LIKE '%finais de semana%' OR
        mensagem LIKE '%meta de economia%' OR
        mensagem LIKE '%iPhone%' OR
        mensagem LIKE '%saldo atual%'
    )");
    
    $stmt->execute([$userId]);
    $deleted = $stmt->rowCount();
    
    echo json_encode(['success' => true, 'message' => "Foram removidos {$deleted} alertas de teste!"]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao limpar alertas: ' . $e->getMessage()]);
}
?>
