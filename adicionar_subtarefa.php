<?php
// /adicionar_subtarefa.php (Versão Moderna com AJAX)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. VERIFICAÇÃO DE SEGURANÇA E VALIDAÇÃO ---
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

$userId = $_SESSION['user_id'];
$id_tarefa_principal = $_POST['id_tarefa_principal'] ?? 0;
$descricao = trim($_POST['descricao'] ?? '');
$prioridade = $_POST['prioridade'] ?? 'Média';
$tempo_estimado = (int)($_POST['tempo_estimado'] ?? 0);

if (empty($descricao) || empty($id_tarefa_principal)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'A descrição e o ID da tarefa principal são obrigatórios.';
    echo json_encode($response);
    exit();
}

try {
    // --- 2. VERIFICA SE A TAREFA PRINCIPAL PERTENCE AO USUÁRIO ---
    // (Sua lógica de segurança original, mantida e aprimorada)
    $stmt_check = $pdo->prepare("SELECT id FROM tarefas WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$id_tarefa_principal, $userId]);
    
    if (!$stmt_check->fetchColumn()) {
        http_response_code(404); // Não Encontrado
        $response['message'] = 'Tarefa principal não encontrada ou não pertence a você.';
        echo json_encode($response);
        exit();
    }
    
    // --- 3. INSERÇÃO NO BANCO DE DADOS ---
    $stmt_insert = $pdo->prepare("INSERT INTO subtarefas (id_tarefa_principal, descricao, prioridade, tempo_estimado) VALUES (?, ?, ?, ?)");
    $stmt_insert->execute([$id_tarefa_principal, $descricao, $prioridade, $tempo_estimado]);
    
    $newSubtaskId = $pdo->lastInsertId();

    // --- 4. RESPOSTA DE SUCESSO ---
    $response['success'] = true;
    $response['message'] = 'Subtarefa adicionada com sucesso!';
    // Retorna os dados da nova subtarefa para o frontend
    $response['subtarefa'] = [
        'id'              => $newSubtaskId,
        'descricao'       => $descricao,
        'prioridade'      => $prioridade,
        'tempo_estimado'  => $tempo_estimado,
        'status'          => 'pendente' // O status padrão do banco
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao adicionar a subtarefa.';
    // Em produção, você logaria o erro em um arquivo: error_log($e->getMessage());
    echo json_encode($response);
}
?>