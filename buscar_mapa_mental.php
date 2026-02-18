<?php
// buscar_mapa_mental.php - Buscar um mapa mental específico
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$mapaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($mapaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'mapas_mentais'");
    if ($stmt_check->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Tabela não encontrada']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, titulo, dados, data_criacao, data_atualizacao 
                          FROM mapas_mentais 
                          WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$mapaId, $userId]);
    $mapa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mapa) {
        // Garantir que dados seja uma string JSON válida
        if (is_array($mapa['dados'])) {
            $mapa['dados'] = json_encode($mapa['dados']);
        } elseif (empty($mapa['dados'])) {
            $mapa['dados'] = '{"nodes":[],"edges":[]}';
        }
        echo json_encode(['success' => true, 'mapa' => $mapa]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mapa mental não encontrado']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>

