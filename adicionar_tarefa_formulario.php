<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Sessão e conexão sem renderizar HTML
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/includes/db_connect.php';

// Aceitar somente POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    // Escolhe o texto principal da tarefa
    $textoTarefa = $titulo !== '' ? $titulo : $descricao;

    $prioridade = trim($_POST['prioridade'] ?? 'Média');
    $data_limite = trim($_POST['data_limite'] ?? '');

    if ($textoTarefa === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Descrição/título é obrigatório']);
        exit;
    }

    if (!in_array($prioridade, ['Baixa', 'Média', 'Alta'], true)) {
        $prioridade = 'Média';
    }

    // Inserir somente na coluna 'descricao' (compatível com o schema existente)
    $stmt = $pdo->prepare("INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, status, data_criacao) VALUES (?, ?, ?, ?, 'pendente', NOW())");
    $stmt->execute([
        $userId,
        $textoTarefa,
        $prioridade,
        $data_limite !== '' ? $data_limite : null,
    ]);

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Tarefa adicionada com sucesso!'], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log('Erro ao adicionar tarefa: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao adicionar tarefa']);
    exit;
} catch (Throwable $e) {
    error_log('Erro geral ao adicionar tarefa: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar requisição']);
    exit;
}
?>
