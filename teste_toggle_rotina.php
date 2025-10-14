<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Simular dados de POST
$_POST['acao'] = 'concluir';
$_POST['rotina_id'] = '1'; // Assumindo que existe uma rotina com ID 1

$userId = $_SESSION['user_id'] ?? null;
$acao = $_POST['acao'] ?? '';
$rotinaId = (int)($_POST['rotina_id'] ?? 0);
$dataHoje = date('Y-m-d');

$debug = [
    'user_id' => $userId,
    'acao' => $acao,
    'rotina_id' => $rotinaId,
    'data_hoje' => $dataHoje
];

try {
    // Verificar se a rotina existe e pertence ao usuário
    $stmt = $pdo->prepare("SELECT id, nome FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$rotinaId, $userId]);
    $rotina = $stmt->fetch();
    
    $debug['rotina_encontrada'] = $rotina ? 'SIM' : 'NÃO';
    $debug['dados_rotina'] = $rotina;
    
    if ($rotina) {
        // Verificar controles existentes
        $stmt = $pdo->prepare("
            SELECT id, status FROM rotina_controle_diario 
            WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
        ");
        $stmt->execute([$userId, $rotinaId, $dataHoje]);
        $controle = $stmt->fetch();
        
        $debug['controle_existente'] = $controle ? 'SIM' : 'NÃO';
        $debug['dados_controle'] = $controle;
        
        // Tentar fazer a operação
        $novoStatus = 'concluido';
        
        if ($controle) {
            // Atualizar existente
            $stmt = $pdo->prepare("
                UPDATE rotina_controle_diario 
                SET status = ?, horario_execucao = NOW() 
                WHERE id = ?
            ");
            $resultado = $stmt->execute([$novoStatus, $controle['id']]);
            $debug['update_resultado'] = $resultado ? 'SUCESSO' : 'ERRO';
            $debug['update_affected_rows'] = $stmt->rowCount();
        } else {
            // Criar novo controle
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status, horario_execucao) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $resultado = $stmt->execute([$userId, $rotinaId, $dataHoje, $novoStatus]);
            $debug['insert_resultado'] = $resultado ? 'SUCESSO' : 'ERRO';
            $debug['insert_id'] = $pdo->lastInsertId();
        }
        
        // Verificar resultado final
        $stmt = $pdo->prepare("
            SELECT status, horario_execucao FROM rotina_controle_diario 
            WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
        ");
        $stmt->execute([$userId, $rotinaId, $dataHoje]);
        $resultado_final = $stmt->fetch();
        
        $debug['resultado_final'] = $resultado_final;
        
    } else {
        $debug['erro'] = 'Rotina não encontrada ou não pertence ao usuário';
    }
    
} catch (PDOException $e) {
    $debug['erro_pdo'] = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
