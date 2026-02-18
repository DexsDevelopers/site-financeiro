<?php
session_start();
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$userId) {
    if ($_GET) {
        header('Location: index.php');
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    }
    exit;
}

// Se for GET, processar deletação com redirecionamento
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    
    if (!$id) {
        header('Location: tarefas.php?error=id_invalido');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        
        if (!$stmt->fetch()) {
            header('Location: tarefas.php?error=rotina_nao_encontrada');
            exit;
        }
        
        // Deletar controles diários
        $stmt = $pdo->prepare("DELETE FROM rotina_controle_diario WHERE id_rotina_fixa = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        
        // Deletar rotina fixa
        $stmt = $pdo->prepare("DELETE FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        
        header('Location: tarefas.php?success=rotina_deletada');
        exit;
    } catch (Exception $e) {
        header('Location: tarefas.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Se for POST com JSON, processar como API
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Rotina não encontrada']);
            exit;
        }
        
        // Deletar controles diários
        $stmt = $pdo->prepare("DELETE FROM rotina_controle_diario WHERE id_rotina_fixa = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        
        // Deletar rotina fixa
        $stmt = $pdo->prepare("DELETE FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Rotina excluída com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>