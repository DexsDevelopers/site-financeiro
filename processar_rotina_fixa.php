<?php
// processar_rotina_fixa.php - Processar ações das rotinas fixas (OTIMIZADO)

session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

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
$dataHoje = date('Y-m-d');

// Verificar se as tabelas existem
try {
    $pdo->query("SELECT 1 FROM rotinas_fixas LIMIT 1");
    $pdo->query("SELECT 1 FROM rotina_controle_diario LIMIT 1");
} catch (PDOException $e) {
    $response['message'] = 'Tabelas não encontradas. Execute o script de criação.';
    $response['debug'] = $e->getMessage();
    echo json_encode($response);
    exit;
}

// Validar parâmetros
if (empty($acao)) {
    $response['message'] = 'Ação não especificada';
    echo json_encode($response);
    exit;
}

if ($rotinaId <= 0 && in_array($acao, ['concluir', 'pendente'])) {
    $response['message'] = 'ID da rotina inválido';
    echo json_encode($response);
    exit;
}

try {
    switch ($acao) {
        case 'concluir':
        case 'pendente':
            $novoStatus = $acao === 'concluir' ? 'concluido' : 'pendente';
            
            // Verificar se a rotina pertence ao usuário
            $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$rotinaId, $userId]);
            if (!$stmt->fetch()) {
                $response['message'] = 'Rotina não encontrada ou não pertence ao usuário';
                break;
            }
            
            // Verificar se já existe controle para hoje
            $stmt = $pdo->prepare("
                SELECT id FROM rotina_controle_diario 
                WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
            ");
            $stmt->execute([$userId, $rotinaId, $dataHoje]);
            $controleExiste = $stmt->fetch();
            
            if ($controleExiste) {
                // Atualizar existente
                $stmt = $pdo->prepare("
                    UPDATE rotina_controle_diario 
                    SET status = ?, horario_execucao = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$novoStatus, $controleExiste['id']]);
            } else {
                // Criar novo controle
                $stmt = $pdo->prepare("
                    INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status, horario_execucao) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$userId, $rotinaId, $dataHoje, $novoStatus]);
            }
            
            $response['success'] = true;
            $response['message'] = $novoStatus === 'concluido' ? 'Rotina marcada como concluída' : 'Rotina marcada como pendente';
            $response['status'] = $novoStatus;
            break;
            
        case 'adicionar':
            $nome = trim($_POST['nome'] ?? '');
            $horario = $_POST['horario'] ?? null;
            $descricao = trim($_POST['descricao'] ?? '') ?: null;
            $ordem = (int)($_POST['ordem'] ?? 0);
            
            if (empty($nome)) {
                $response['message'] = 'Nome da rotina é obrigatório';
                break;
            }
            
            // Inserir rotina fixa
            $stmt = $pdo->prepare("
                INSERT INTO rotinas_fixas (id_usuario, nome, horario_sugerido, descricao, ordem, ativo) 
                VALUES (?, ?, ?, ?, ?, TRUE)
            ");
            
            if ($stmt->execute([$userId, $nome, $horario, $descricao, $ordem])) {
                $idRotina = $pdo->lastInsertId();
                
                // Criar controle para hoje
                $stmt = $pdo->prepare("
                    INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                    VALUES (?, ?, ?, 'pendente')
                ");
                $stmt->execute([$userId, $idRotina, $dataHoje]);
                
                $response['success'] = true;
                $response['message'] = 'Rotina adicionada com sucesso';
                $response['rotina_id'] = $idRotina;
            } else {
                $response['message'] = 'Erro ao adicionar rotina';
            }
            break;
            
        default:
            $response['message'] = 'Ação não reconhecida';
    }
    
} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    $response['debug'] = [
        'acao' => $acao,
        'rotina_id' => $rotinaId,
        'user_id' => $userId,
        'data_hoje' => $dataHoje,
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    ];
    error_log("Erro ao processar rotina fixa: " . $e->getMessage());
    error_log("Debug info: " . json_encode($response['debug']));
}

echo json_encode($response);
?>
