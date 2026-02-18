<?php
// /adicionar_tarefa.php (Versão AJAX Completa e Final)



session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro desconhecido.'];

// --- Validações Iniciais ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Faça o login novamente.';
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

// --- Coleta e Validação Completa dos Dados do Formulário ---
$userId = $_SESSION['user_id'];
$descricao = trim($_POST['descricao'] ?? '');
$prioridade = $_POST['prioridade'] ?? 'Média'; // Valor padrão caso não seja enviado
$data_limite = !empty($_POST['data_limite']) ? $_POST['data_limite'] : null;

// Lógica completa para calcular o tempo total em minutos a partir dos campos de horas e minutos
$horas = !empty($_POST['tempo_horas']) ? (int)$_POST['tempo_horas'] : 0;
$minutos = !empty($_POST['tempo_minutos']) ? (int)$_POST['tempo_minutos'] : 0;
$tempo_estimado_total = ($horas * 60) + $minutos;

// Se o tempo total for 0, salvamos NULL (nulo) no banco de dados
if ($tempo_estimado_total <= 0) {
    $tempo_estimado_total = null;
}

if (empty($descricao)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'A descrição da tarefa é obrigatória.';
    echo json_encode($response);
    exit();
}

// --- Inserção no Banco de Dados ---
try {
    // A query agora inclui todos os campos, incluindo a 'ordem' para o drag-and-drop
    $sql = "INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, tempo_estimado, ordem) VALUES (?, ?, ?, ?, ?, 0)";
    $stmt = $pdo->prepare($sql);
    
    // Executa a query com todos os valores coletados
    $stmt->execute([$userId, $descricao, $prioridade, $data_limite, $tempo_estimado_total]);
    
    $newTaskId = $pdo->lastInsertId();

    // Resposta de sucesso com os dados da nova tarefa
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
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao salvar a tarefa.';
    // Em produção, você logaria o erro em um arquivo: error_log($e->getMessage());
    echo json_encode($response);
}
?>