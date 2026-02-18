<?php
// /atualizar_ordem_tarefas.php (Versão Profissional Refinada)

session_start();
header('Content-Type: application/json');

// Padroniza a estrutura da resposta, como nos outros scripts
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- Validação de Acesso ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- Validação de Entrada ---
$input = json_decode(file_get_contents('php://input'), true);
$ordemTarefas = $input['ordem'] ?? [];
$userId = $_SESSION['user_id'];

if (empty($ordemTarefas) || !is_array($ordemTarefas)) {
    http_response_code(400);
    $response['message'] = 'Nenhuma ordem de tarefas recebida ou o formato é inválido.';
    echo json_encode($response);
    exit();
}

try {
    // --- ATUALIZAÇÃO SEGURA COM TRANSAÇÃO DE BANCO DE DADOS ---
    // Uma transação garante que TODAS as atualizações de ordem sejam bem-sucedidas.
    // Se uma única falhar no meio do processo, o banco desfaz TODAS as outras,
    // impedindo que a ordem das tarefas fique inconsistente. É uma prática de alta segurança.
    $pdo->beginTransaction(); 

    $sql = "UPDATE tarefas SET ordem = ? WHERE id = ? AND id_usuario = ?";
    $stmt = $pdo->prepare($sql);

    // Loop através do array de IDs recebido do JavaScript e atualiza a coluna 'ordem'
    foreach ($ordemTarefas as $indice => $tarefaId) {
        // Garante que o ID é um número para segurança extra
        $stmt->execute([$indice, (int)$tarefaId, $userId]);
    }

    // Se todas as queries do loop funcionaram, confirma as alterações no banco de uma só vez.
    $pdo->commit(); 

    // --- Resposta de Sucesso Padronizada ---
    $response['success'] = true;
    $response['message'] = 'Ordem das tarefas atualizada com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    // Se qualquer erro ocorrer durante o processo, desfaz todas as alterações que já foram feitas.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar a ordem.';
    // Em produção: error_log($e->getMessage());
    echo json_encode($response);
}
?>