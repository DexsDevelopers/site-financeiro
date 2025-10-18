<?php
// /adicionar_subtarefa.php (Versão Moderna com AJAX)

session_start();
header('Content-Type: application/json; charset=utf-8');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. VERIFICAÇÃO DE SEGURANÇA E VALIDAÇÃO ---
$userId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
if (!$userId) {
    http_response_code(403); // Proibido
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Sessão não encontrada.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
    exit();
}

require_once 'includes/db_connect.php';

// Aceitar tanto form-data/x-www-form-urlencoded quanto JSON
$conteudoTipo = $_SERVER['CONTENT_TYPE'] ?? '';
$id_tarefa_principal = null;
$descricao = '';
$prioridade = 'Média';
$tempo_estimado = 0;

if (stripos($conteudoTipo, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $id_tarefa_principal = $data['id_tarefa_principal'] ?? $data['tarefa_id'] ?? null;
    $descricao = trim($data['descricao'] ?? '');
    $prioridade = $data['prioridade'] ?? 'Média';
    $tempo_estimado = (int)($data['tempo_estimado'] ?? 0);
} else {
    $id_tarefa_principal = $_POST['id_tarefa_principal'] ?? $_POST['tarefa_id'] ?? null;
    $descricao = trim($_POST['descricao'] ?? '');
    $prioridade = $_POST['prioridade'] ?? 'Média';
    $tempo_estimado = (int)($_POST['tempo_estimado'] ?? 0);
}

$id_tarefa_principal = is_numeric($id_tarefa_principal) ? (int)$id_tarefa_principal : 0;

if ($id_tarefa_principal <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da tarefa principal inválido.']);
    exit();
}

if ($descricao === '') {
    http_response_code(400); // Requisição Inválida
    echo json_encode(['success' => false, 'message' => 'A descrição da subtarefa é obrigatória.']);
    exit();
}

try {
    // --- 2. VERIFICA SE A TAREFA PRINCIPAL PERTENCE AO USUÁRIO ---
    $stmt_check = $pdo->prepare("SELECT id FROM tarefas WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$id_tarefa_principal, $userId]);

    if (!$stmt_check->fetchColumn()) {
        http_response_code(404); // Não Encontrado
        echo json_encode(['success' => false, 'message' => 'Tarefa principal não encontrada ou não pertence a você.']);
        exit();
    }

    // --- 3. INSERÇÃO NO BANCO DE DADOS ---
    $stmt_insert = $pdo->prepare("INSERT INTO subtarefas (id_tarefa_principal, descricao, prioridade, tempo_estimado) VALUES (?, ?, ?, ?)");
    $stmt_insert->execute([$id_tarefa_principal, $descricao, $prioridade, $tempo_estimado]);

    $newSubtaskId = $pdo->lastInsertId();

    // --- 4. RESPOSTA DE SUCESSO ---
    echo json_encode([
        'success' => true,
        'message' => 'Subtarefa adicionada com sucesso!',
        'subtarefa' => [
            'id' => $newSubtaskId,
            'descricao' => $descricao,
            'prioridade' => $prioridade,
            'tempo_estimado' => $tempo_estimado,
            'status' => 'pendente'
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados ao adicionar a subtarefa.'
    ]);
}
?>