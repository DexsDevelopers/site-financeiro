<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once 'includes/db_connect.php';

$acao = trim($_POST['acao'] ?? '');

try {
    switch ($acao) {
        case 'criar':
            $nome = trim($_POST['nome'] ?? '');
            $horario = $_POST['horario'] ?? null;
            $descricao = trim($_POST['descricao'] ?? '');
            
            if (!$nome) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO rotinas_fixas (id_usuario, nome, horario_sugerido, descricao, ativo)
                VALUES (?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$userId, $nome, $horario ?: null, $descricao ?: null]);
            
            echo json_encode(['success' => true, 'message' => 'Rotina criada!', 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'editar':
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $horario = $_POST['horario'] ?? null;
            $descricao = trim($_POST['descricao'] ?? '');
            
            if (!$id || !$nome) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID e nome são obrigatórios']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE rotinas_fixas 
                SET nome = ?, horario_sugerido = ?, descricao = ?
                WHERE id = ? AND id_usuario = ?
            ");
            $stmt->execute([$nome, $horario ?: null, $descricao ?: null, $id, $userId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Rotina atualizada!']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Rotina não encontrada']);
            }
            break;
            
        case 'deletar':
            $id = (int)($_POST['id'] ?? 0);
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID é obrigatório']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM rotinas_fixas
                WHERE id = ? AND id_usuario = ?
            ");
            $stmt->execute([$id, $userId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Rotina deletada!']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Rotina não encontrada']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (PDOException $e) {
    error_log('Erro em gerenciar_rotina_fixa.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar']);
}
?>
