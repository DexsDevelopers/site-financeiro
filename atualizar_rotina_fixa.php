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

    $stmt = $pdo->prepare("
        UPDATE rotinas_fixas 
        SET nome = ?, horario_sugerido = ?, descricao = ?
        WHERE id = ? AND id_usuario = ?
    ");
    $stmt->execute([$nome, $horario, $descricao, $rotinaId, $userId]);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Rotina atualizada com sucesso']);

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
