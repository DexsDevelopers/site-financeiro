<?php
require_once 'includes/db_connect.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $controleId = (int)($_POST['controle_id'] ?? 0);
    $rotinaId = (int)($_POST['rotina_id'] ?? 0);
    $requestedStatus = trim($_POST['status'] ?? 'concluido');
    $criarControle = isset($_POST['criar_controle']) && $_POST['criar_controle'] == '1';
    $dataHoje = date('Y-m-d');
    
    // Se não tem controle_id mas tem rotina_id, buscar ou criar controle
    if (!$controleId && $rotinaId) {
        // Buscar controle existente para hoje
        $stmt = $pdo->prepare("
            SELECT id, status FROM rotina_controle_diario 
            WHERE id_rotina_fixa = ? AND id_usuario = ? AND data_execucao = ?
        ");
        $stmt->execute([$rotinaId, $userId, $dataHoje]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $controleId = $row['id'];
            $statusAtual = $row['status'];
        } else if ($criarControle) {
            // Criar novo controle
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status)
                VALUES (?, ?, ?, ?)
            ");
            $novoStatus = $requestedStatus;
            $stmt->execute([$userId, $rotinaId, $dataHoje, $novoStatus]);
            $controleId = $pdo->lastInsertId();
            $statusAtual = 'pendente';
        } else {
            http_response_code(404);
            throw new Exception('Controle diário não encontrado. Tente novamente.');
        }
    } else if (!$controleId) {
        http_response_code(400);
        throw new Exception('ID de controle ou rotina inválido');
    } else {
        // ===== BUSCAR STATUS ATUAL =====
        $stmt = $pdo->prepare("
            SELECT status FROM rotina_controle_diario 
            WHERE id = ? AND id_usuario = ?
        ");
        $stmt->execute([$controleId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            http_response_code(404);
            throw new Exception('Rotina não encontrada');
        }
        
        $statusAtual = $row['status'];
    }
    
    // ===== DETERMINAR NOVO STATUS =====
    // Se o status atual é "concluido" e o usuário clica novamente, volta para "pendente"
    // Se o status atual é "pendente" e o usuário clica, vai para "concluido"
    if ($statusAtual === 'concluido') {
        $novoStatus = 'pendente';
    } else {
        $novoStatus = 'concluido';
    }
    
    // ===== ATUALIZAR STATUS =====
    $stmt = $pdo->prepare("
        UPDATE rotina_controle_diario 
        SET status = ? 
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$novoStatus, $controleId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        $mensagem = $novoStatus === 'concluido' 
            ? 'Rotina marcada como concluída!' 
            : 'Rotina revertida para pendente!';
        
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => $mensagem,
            'status_anterior' => $statusAtual,
            'status_novo' => $novoStatus
        ]);
    } else {
        http_response_code(500);
        throw new Exception('Erro ao atualizar status');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
