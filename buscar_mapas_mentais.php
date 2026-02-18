<?php
// buscar_mapas_mentais.php - Buscar mapas mentais do usuário
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'mapas_mentais'");
    if ($stmt_check->rowCount() == 0) {
        echo json_encode(['success' => true, 'mapas' => []]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, titulo, dados, data_criacao, data_atualizacao 
                          FROM mapas_mentais 
                          WHERE id_usuario = ? 
                          ORDER BY data_atualizacao DESC");
    $stmt->execute([$userId]);
    $mapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Garantir que dados seja sempre uma string JSON válida
    foreach ($mapas as &$mapa) {
        if (is_array($mapa['dados'])) {
            $mapa['dados'] = json_encode($mapa['dados']);
        } elseif (empty($mapa['dados'])) {
            $mapa['dados'] = '{"nodes":[],"edges":[]}';
        }
    }
    unset($mapa);
    
    echo json_encode(['success' => true, 'mapas' => $mapas]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>

