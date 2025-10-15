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
        case 'adicionar_rotina_fixa':
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $horarioSugerido = $_POST['horario_sugerido'] ?: null;
            $diasSemana = isset($_POST['dias_semana']) ? implode(',', $_POST['dias_semana']) : null;
            $cor = $_POST['cor'] ?? '#007bff';
            $icone = $_POST['icone'] ?? 'bi-check-circle';
            
            if (empty($nome)) {
                throw new Exception('Nome da rotina é obrigatório');
            }
            
            // Verificar se já existe
            $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id_usuario = ? AND nome = ?");
            $stmt->execute([$userId, $nome]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma rotina fixa com este nome');
            }
            
            // Inserir rotina fixa
            $stmt = $pdo->prepare("
                INSERT INTO rotinas_fixas (id_usuario, nome, descricao, horario_sugerido, dias_semana, cor, icone) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $nome, $descricao, $horarioSugerido, $diasSemana, $cor, $icone]);
            
            echo json_encode(['success' => true, 'message' => 'Rotina fixa adicionada com sucesso!']);
            break;
            
        case 'concluir':
        case 'pendente':
            $rotinaId = $_POST['rotina_id'] ?? 0;
            $novoStatus = $acao === 'concluir' ? 'concluido' : 'pendente';
            $dataHoje = date('Y-m-d');
            
            // Verificar se a rotina pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$rotinaId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Rotina não encontrada');
            }
            
            // Atualizar ou criar controle diário
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status, horario_execucao) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                horario_execucao = CASE WHEN VALUES(status) = 'concluido' THEN NOW() ELSE horario_execucao END
            ");
            $stmt->execute([$userId, $rotinaId, $dataHoje, $novoStatus]);
            
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