<?php
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];
$acao = $_POST['acao'] ?? '';

try {
    switch ($acao) {
        case 'adicionar_rotina_diaria':
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $horario = $_POST['horario'] ?: null;
            $prioridade = $_POST['prioridade'] ?? 'media';
            $cor = $_POST['cor'] ?? '#28a745';
            $icone = $_POST['icone'] ?? 'bi-calendar-day';
            $dataHoje = date('Y-m-d');
            
            if (empty($nome)) {
                throw new Exception('Nome da rotina é obrigatório');
            }
            
            // Inserir rotina diária
            $stmt = $pdo->prepare("
                INSERT INTO rotinas_diarias (id_usuario, nome, descricao, horario, prioridade, cor, icone, data_execucao) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $nome, $descricao, $horario, $prioridade, $cor, $icone, $dataHoje]);
            
            echo json_encode(['success' => true, 'message' => 'Rotina diária adicionada com sucesso!']);
            break;
            
        case 'toggle':
            $id = $_POST['id'] ?? 0;
            $novoStatus = $_POST['status'] ?? 'pendente';
            
            // Verificar se a rotina pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM rotinas_diarias WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Rotina não encontrada');
            }
            
            // Atualizar status
            $stmt = $pdo->prepare("UPDATE rotinas_diarias SET status = ? WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$novoStatus, $id, $userId]);
            
            $mensagem = $novoStatus === 'concluido' ? 'Rotina marcada como concluída!' : 'Rotina marcada como pendente!';
            echo json_encode(['success' => true, 'message' => $mensagem]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
