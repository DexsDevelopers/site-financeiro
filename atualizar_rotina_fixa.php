<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

require_once 'includes/db_connect.php';

try {
    // Receber JSON POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    $rotinaId = intval($input['id'] ?? 0);
    $nome = trim($input['nome'] ?? '');
    $horario = $input['horario'] ?? null;
    $descricao = trim($input['descricao'] ?? '');

    if (!$rotinaId || !$nome) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos']);
        exit;
    }

    // Converter horário vazio ou 00:00 para NULL
    if (empty($horario) || $horario === '00:00') {
        $horario = null;
    }

    // Debug logging
    error_log("=== ATUALIZAR ROTINA FIXA ===");
    error_log("ID: $rotinaId, Usuário: $userId");
    error_log("Horário recebido: " . var_export($input['horario'], true));
    error_log("Horário a salvar: " . var_export($horario, true));
    
    $stmt = $pdo->prepare("
        UPDATE rotinas_fixas 
        SET nome = ?, horario_sugerido = ?, descricao = ?
        WHERE id = ? AND id_usuario = ?
    ");
    
    error_log("SQL: UPDATE rotinas_fixas SET nome = ?, horario_sugerido = ?, descricao = ? WHERE id = $rotinaId AND id_usuario = $userId");
    error_log("Parâmetros: " . json_encode([$nome, $horario, $descricao, $rotinaId, $userId]));
    
    $result = $stmt->execute([$nome, $horario, $descricao, $rotinaId, $userId]);
    $rowsAffected = $stmt->rowCount();
    
    error_log("Rows affected: $rowsAffected");
    
    // Verificar o que foi salvo
    $stmtVerify = $pdo->prepare("SELECT id, nome, horario_sugerido, descricao FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmtVerify->execute([$rotinaId, $userId]);
    $rotinaVerify = $stmtVerify->fetch(PDO::FETCH_ASSOC);
    error_log("Dados após UPDATE: " . json_encode($rotinaVerify));
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Rotina atualizada com sucesso', 'debug' => $rotinaVerify]);

} catch (PDOException $e) {
    error_log("Erro ao atualizar rotina fixa: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar rotina']);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar requisição']);
}
?>
