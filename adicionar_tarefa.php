<?php
// /adicionar_tarefa.php (Versão AJAX Compatível)

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro desconhecido.'];

// Autenticação (aceita chaves alternativas)
$userId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
if (!$userId) {
    http_response_code(403);
    $response['message'] = 'Acesso negado. Faça o login novamente.';
    echo json_encode($response);
    exit();
}

// Método
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// Dados
$descricao = trim($_POST['descricao'] ?? '');
$prioridade = $_POST['prioridade'] ?? 'Média';
$data_limite = !empty($_POST['data_limite']) ? $_POST['data_limite'] : null;

$horas = isset($_POST['tempo_horas']) ? (int)$_POST['tempo_horas'] : 0;
$minutos = isset($_POST['tempo_minutos']) ? (int)$_POST['tempo_minutos'] : 0;
$tempo_estimado_total = ($horas * 60) + $minutos;
if ($tempo_estimado_total <= 0) { $tempo_estimado_total = null; }

if ($descricao === '') {
    http_response_code(400);
    $response['message'] = 'A descrição da tarefa é obrigatória.';
    echo json_encode($response);
    exit();
}

// Inserção robusta com fallback de colunas
try {
    $sqls = [
        [
            "INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, tempo_estimado, ordem, status, data_criacao) VALUES (?, ?, ?, ?, ?, 0, 'pendente', NOW())",
            [$userId, $descricao, $prioridade, $data_limite, $tempo_estimado_total]
        ],
        [
            "INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, tempo_estimado, status, data_criacao) VALUES (?, ?, ?, ?, ?, 'pendente', NOW())",
            [$userId, $descricao, $prioridade, $data_limite, $tempo_estimado_total]
        ],
        [
            "INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, status) VALUES (?, ?, ?, ?, 'pendente')",
            [$userId, $descricao, $prioridade, $data_limite]
        ],
        [
            "INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite) VALUES (?, ?, ?, ?)",
            [$userId, $descricao, $prioridade, $data_limite]
        ],
    ];

    $insertOk = false;
    foreach ($sqls as [$sql, $params]) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $insertOk = true;
            break;
        } catch (PDOException $e) {
            // tenta próximo formato
            continue;
        }
    }

    if (!$insertOk) {
        throw new PDOException('Falha ao inserir tarefa em todos os formatos conhecidos.');
    }

    $newTaskId = $pdo->lastInsertId();

    $response['success'] = true;
    $response['message'] = 'Tarefa adicionada com sucesso!';
    $response['tarefa'] = [
        'id'             => $newTaskId,
        'descricao'      => $descricao,
        'prioridade'     => $prioridade,
        'data_limite'    => $data_limite,
        'tempo_estimado' => $tempo_estimado_total,
        'status'         => 'pendente'
    ];
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao salvar a tarefa.';
    // Log seguro
    error_log('[ERRO][adicionar_tarefa.php] ' . $e->getMessage());
    echo json_encode($response);
}
?>