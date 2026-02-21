<?php
// /atualizar_status_tarefa.php (Versão Moderna com AJAX)

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. Verificações de Acesso e Método ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

// Para AJAX, mudamos para POST para alterar dados
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- 2. Coleta e Validação dos Dados ---
// Os dados agora vêm do corpo da requisição JSON, não da URL
$input = json_decode(file_get_contents('php://input'), true);
$tarefaId = $input['id'] ?? 0;
$novoStatus = $input['status'] ?? '';
$userId = $_SESSION['user_id'];

if (empty($tarefaId) || !is_numeric($tarefaId) || !in_array($novoStatus, ['pendente', 'concluida'])) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Dados inválidos. ID da tarefa e novo status são obrigatórios.';
    echo json_encode($response);
    exit();
}

// --- 3. Atualização no Banco de Dados ---
try {
    if (strpos($tarefaId, 'ge-') === 0) {
        // Tarefa da Gestão de Empresas
        $realId = (int)str_replace('ge-', '', $tarefaId);

        // Mapear status: tarefas usa 'concluido', ge_tarefas usa 'concluida'
        $statusGE = ($novoStatus === 'concluida' || $novoStatus === 'concluido') ? 'concluida' : 'pendente';

        $stmt = $pdo->prepare("UPDATE ge_tarefas gt 
                               JOIN ge_empresas e ON gt.id_empresa = e.id 
                               SET gt.status = ? 
                               WHERE gt.id = ? AND e.id_usuario = ?");
        $stmt->execute([$statusGE, $realId, $userId]);
    }
    else {
        // Tarefa Normal
        // Garantir que o status mapeie para o enum da tabela tarefas ('concluido')
        $statusNormal = ($novoStatus === 'concluida' || $novoStatus === 'concluido') ? 'concluido' : 'pendente';

        $stmt = $pdo->prepare("UPDATE tarefas SET status = ? WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$statusNormal, (int)$tarefaId, $userId]);
    }

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Status da tarefa atualizado com sucesso!';
    }
    else {
        // Se já estava com o status desejado, rowCount é 0, mas consideramos sucesso
        $response['success'] = true;
        $response['message'] = 'Status atualizado!';
    }

    echo json_encode($response);

}
catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao atualizar a tarefa.';
    // error_log($e->getMessage()); // Em produção
    echo json_encode($response);
}
?>