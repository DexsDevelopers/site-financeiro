<?php
// processar_rotina_hoje.php - Processar ações das rotinas de hoje

session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ação inválida'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido';
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    $response['message'] = 'Usuário não logado';
    echo json_encode($response);
    exit;
}

$acao = $_POST['acao'] ?? '';
$rotinaId = (int)($_POST['rotina_id'] ?? 0);
$observacoes = $_POST['observacoes'] ?? '';

try {
    switch ($acao) {
        case 'concluir':
            $stmt = $pdo->prepare("
                UPDATE rotina_diaria 
                SET status = 'concluido', horario_execucao = NOW(), observacoes = ?
                WHERE id = ? AND id_usuario = ?
            ");
            if ($stmt->execute([$observacoes, $rotinaId, $userId])) {
                $response['success'] = true;
                $response['message'] = 'Rotina marcada como concluída';
                $response['status'] = 'concluido';
                $response['horario'] = date('H:i:s');
            } else {
                $response['message'] = 'Erro ao marcar como concluída';
            }
            break;
            
        case 'pular':
            $stmt = $pdo->prepare("
                UPDATE rotina_diaria 
                SET status = 'pulado', observacoes = ?
                WHERE id = ? AND id_usuario = ?
            ");
            if ($stmt->execute([$observacoes, $rotinaId, $userId])) {
                $response['success'] = true;
                $response['message'] = 'Rotina pulada';
                $response['status'] = 'pulado';
            } else {
                $response['message'] = 'Erro ao pular rotina';
            }
            break;
            
        case 'pendente':
            $stmt = $pdo->prepare("
                UPDATE rotina_diaria 
                SET status = 'pendente', horario_execucao = NULL, observacoes = ?
                WHERE id = ? AND id_usuario = ?
            ");
            if ($stmt->execute([$observacoes, $rotinaId, $userId])) {
                $response['success'] = true;
                $response['message'] = 'Rotina marcada como pendente';
                $response['status'] = 'pendente';
            } else {
                $response['message'] = 'Erro ao marcar como pendente';
            }
            break;
            
        case 'adicionar':
            $nome = $_POST['nome'] ?? '';
            $horario = $_POST['horario'] ?? null;
            $ordem = (int)($_POST['ordem'] ?? 0);
            $dataHoje = date('Y-m-d');
            
            if (empty($nome)) {
                $response['message'] = 'Nome da rotina é obrigatório';
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO rotina_diaria (id_usuario, nome, data_execucao, horario, ordem, status) 
                VALUES (?, ?, ?, ?, ?, 'pendente')
            ");
            
            if ($stmt->execute([$userId, $nome, $dataHoje, $horario, $ordem])) {
                $response['success'] = true;
                $response['message'] = 'Rotina adicionada com sucesso';
                $response['rotina_id'] = $pdo->lastInsertId();
            } else {
                $response['message'] = 'Erro ao adicionar rotina';
            }
            break;
            
        case 'remover':
            $stmt = $pdo->prepare("DELETE FROM rotina_diaria WHERE id = ? AND id_usuario = ?");
            if ($stmt->execute([$rotinaId, $userId])) {
                $response['success'] = true;
                $response['message'] = 'Rotina removida com sucesso';
            } else {
                $response['message'] = 'Erro ao remover rotina';
            }
            break;
            
        default:
            $response['message'] = 'Ação não reconhecida';
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    error_log("Erro ao processar rotina de hoje: " . $e->getMessage());
}

echo json_encode($response);
?>
