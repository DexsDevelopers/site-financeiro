<?php
// /atualizar_data_tarefa.php (Versão Profissional Refinada)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Erro desconhecido.'];

// --- Validações Iniciais de Acesso ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- Coleta e Validação dos Dados ---
$input = json_decode(file_get_contents('php://input'), true);
$tarefaId = $input['id'] ?? 0;
$novaData = $input['nova_data'] ?? '';
$userId = $_SESSION['user_id'];

if (empty($tarefaId) || empty($novaData)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Dados inválidos. ID da tarefa e nova data são obrigatórios.';
    echo json_encode($response);
    exit();
}

// VALIDAÇÃO ADICIONAL: Verifica se a data recebida está no formato correto (YYYY-MM-DD)
$d = DateTime::createFromFormat('Y-m-d', $novaData);
if (!$d || $d->format('Y-m-d') !== $novaData) {
    http_response_code(400);
    $response['message'] = 'Formato de data recebido é inválido.';
    echo json_encode($response);
    exit();
}

// --- Atualização no Banco de Dados ---
try {
    // A query UPDATE é segura, pois verifica o id da tarefa E o id do usuário
    $stmt = $pdo->prepare("UPDATE tarefas SET data_limite = ? WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$novaData, $tarefaId, $userId]);

    // Verifica se alguma linha foi realmente alterada
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Data da tarefa atualizada com sucesso!';
    } else {
        // Se rowCount for 0, significa que a tarefa não foi encontrada para este usuário
        http_response_code(404); // Não Encontrado
        $response['message'] = 'Tarefa não encontrada ou você não tem permissão para alterá-la.';
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao atualizar a tarefa.';
    // Em produção, você logaria o erro em vez de exibi-lo:
    // error_log("Erro ao atualizar data da tarefa: " . $e->getMessage());
    echo json_encode($response);
}
?>