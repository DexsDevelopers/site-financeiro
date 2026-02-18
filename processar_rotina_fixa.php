<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Verificar autenticação (compatível com ambos os formatos)
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}
$acao = $_POST['acao'] ?? '';

try {
    switch ($acao) {
        case 'adicionar':
        case 'adicionar_rotina_fixa':
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $horarioSugerido = $_POST['horario'] ?? $_POST['horario_sugerido'] ?? null;
            
            // Converter horário vazio para NULL
            if (empty($horarioSugerido) || $horarioSugerido === '00:00') {
                $horarioSugerido = null;
            }
            
            if (empty($nome)) {
                throw new Exception('Nome do hábito é obrigatório');
            }
            
            // Inserir rotina fixa (sem verificar duplicata para permitir hábitos com mesmo nome)
            $stmt = $pdo->prepare("
                INSERT INTO rotinas_fixas (id_usuario, nome, descricao, horario_sugerido, ativo) 
                VALUES (?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([$userId, $nome, $descricao, $horarioSugerido]);
            
            echo json_encode(['success' => true, 'message' => 'Hábito adicionado com sucesso!']);
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