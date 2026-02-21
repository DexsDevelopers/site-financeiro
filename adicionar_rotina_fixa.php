<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro desconhecido.'];

// --- 1. Verificação de Autenticação ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

// --- 2. Verificar Método POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- 3. Coleta e Validação de Dados ---
$userId = $_SESSION['user_id'];
$nome = trim($_POST['nome'] ?? '');
$horario = !empty($_POST['horario']) ? $_POST['horario'] : null;
$descricao = trim($_POST['descricao'] ?? '');
$prioridade = $_POST['prioridade'] ?? 'Média';

// Debug logging
error_log("=== ADICIONAR ROTINA FIXA ===");
error_log("POST data: " . json_encode($_POST));
error_log("Nome recebido: '$nome'");
error_log("Horário recebido: '$horario'");
error_log("Descrição recebida: '$descricao'");

if (empty($nome)) {
    http_response_code(400);
    $response['message'] = 'O nome da rotina é obrigatório.';
    echo json_encode($response);
    exit();
}

// --- 4. Inserir Rotina Fixa ---
try {
    $stmt = $pdo->prepare("
        INSERT INTO rotinas_fixas (id_usuario, nome, horario_sugerido, prioridade, descricao, ativo, data_criacao)
        VALUES (?, ?, ?, ?, ?, TRUE, NOW())
    ");
    $stmt->execute([$userId, $nome, $horario, $prioridade, $descricao ?: null]);

    $newRotinaId = $pdo->lastInsertId();

    // --- 5. Criar Controle Diário para Hoje ---
    $dataHoje = date('Y-m-d');
    $stmt = $pdo->prepare("
        INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status)
        VALUES (?, ?, ?, 'pendente')
    ");
    $stmt->execute([$userId, $newRotinaId, $dataHoje]);

    // --- 6. Resposta de Sucesso ---
    $response['success'] = true;
    $response['message'] = 'Rotina fixa criada com sucesso!';
    $response['rotina'] = [
        'id' => $newRotinaId,
        'nome' => $nome,
        'horario_sugerido' => $horario,
        'descricao' => $descricao,
        'ativo' => true,
        'status_hoje' => 'pendente'
    ];
    echo json_encode($response);

}
catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro ao criar rotina fixa.';
    error_log('[ERRO][adicionar_rotina_fixa.php] ' . $e->getMessage());
    echo json_encode($response);
}
?>
