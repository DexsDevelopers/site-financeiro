<?php
// processar_rotina_fixa.php - Processar ações das rotinas fixas

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/rotina_fixa_functions.php';

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
            if (atualizarStatusRotinaFixa($pdo, $userId, $rotinaId, 'concluido', $observacoes)) {
                $response['success'] = true;
                $response['message'] = 'Rotina marcada como concluída';
                $response['status'] = 'concluido';
                $response['horario'] = date('H:i:s');
            } else {
                $response['message'] = 'Erro ao marcar como concluída';
            }
            break;
            
        case 'pular':
            if (atualizarStatusRotinaFixa($pdo, $userId, $rotinaId, 'pulado', $observacoes)) {
                $response['success'] = true;
                $response['message'] = 'Rotina pulada';
                $response['status'] = 'pulado';
            } else {
                $response['message'] = 'Erro ao pular rotina';
            }
            break;
            
        case 'pendente':
            if (atualizarStatusRotinaFixa($pdo, $userId, $rotinaId, 'pendente', $observacoes)) {
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
            $descricao = $_POST['descricao'] ?? null;
            $ordem = (int)($_POST['ordem'] ?? 0);
            
            if (empty($nome)) {
                $response['message'] = 'Nome da rotina é obrigatório';
                break;
            }
            
            $idRotina = adicionarRotinaFixa($pdo, $userId, $nome, $horario, $descricao, $ordem);
            if ($idRotina) {
                $response['success'] = true;
                $response['message'] = 'Rotina adicionada com sucesso';
                $response['rotina_id'] = $idRotina;
            } else {
                $response['message'] = 'Erro ao adicionar rotina';
            }
            break;
            
        case 'remover':
            if (removerRotinaFixa($pdo, $userId, $rotinaId)) {
                $response['success'] = true;
                $response['message'] = 'Rotina removida com sucesso';
            } else {
                $response['message'] = 'Erro ao remover rotina';
            }
            break;
            
        case 'toggle':
            $ativo = $_POST['ativo'] === 'true';
            if (toggleRotinaFixa($pdo, $userId, $rotinaId, $ativo)) {
                $response['success'] = true;
                $response['message'] = $ativo ? 'Rotina ativada' : 'Rotina desativada';
                $response['ativo'] = $ativo;
            } else {
                $response['message'] = 'Erro ao alterar status da rotina';
            }
            break;
            
        default:
            $response['message'] = 'Ação não reconhecida';
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    error_log("Erro ao processar rotina fixa: " . $e->getMessage());
}

echo json_encode($response);
?>
