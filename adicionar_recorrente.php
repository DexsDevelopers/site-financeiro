<?php
// /adicionar_recorrente.php (Versão Moderna com AJAX)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. VERIFICAÇÃO DE SEGURANÇA ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- 2. VALIDAÇÃO DOS DADOS DE ENTRADA ---
$userId = $_SESSION['user_id'];
$id_categoria = $_POST['id_categoria'] ?? null;
$descricao = trim($_POST['descricao'] ?? '');
$valor = $_POST['valor'] ?? null;
$dia_execucao = $_POST['dia_execucao'] ?? null;

if (empty($id_categoria) || empty($descricao) || !is_numeric($valor) || $valor <= 0 || !is_numeric($dia_execucao)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Todos os campos são obrigatórios e os valores devem ser válidos.';
    echo json_encode($response);
    exit();
}

if ($dia_execucao < 1 || $dia_execucao > 31) {
    http_response_code(400);
    $response['message'] = 'O dia de execução deve ser um número entre 1 e 31.';
    echo json_encode($response);
    exit();
}

try {
    // --- 3. VERIFICAÇÕES DE INTEGRIDADE NO BANCO ---

    // a) A categoria selecionada pertence ao usuário?
    $stmt_cat_check = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt_cat_check->execute([$id_categoria, $userId]);
    if (!$stmt_cat_check->fetch()) {
        http_response_code(403); // Proibido
        $response['message'] = 'A categoria selecionada é inválida ou não pertence a você.';
        echo json_encode($response);
        exit();
    }

    // b) Já existe uma recorrência com a mesma descrição para este usuário? (Evita duplicatas)
    $stmt_dup_check = $pdo->prepare("SELECT id FROM transacoes_recorrentes WHERE id_usuario = ? AND descricao = ?");
    $stmt_dup_check->execute([$userId, $descricao]);
    if ($stmt_dup_check->fetch()) {
        http_response_code(409); // Conflito
        $response['message'] = 'Uma transação recorrente com essa mesma descrição já existe.';
        echo json_encode($response);
        exit();
    }

    // --- 4. INSERÇÃO NO BANCO DE DADOS ---
    $sql = "INSERT INTO transacoes_recorrentes (id_usuario, id_categoria, descricao, valor, dia_execucao, data_inicio) 
            VALUES (?, ?, ?, ?, ?, CURDATE())";
    $stmt_insert = $pdo->prepare($sql);
    $stmt_insert->execute([$userId, $id_categoria, $descricao, $valor, $dia_execucao]);

    // --- 5. RESPOSTA DE SUCESSO ---
    $response['success'] = true;
    $response['message'] = 'Transação recorrente criada com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao salvar a recorrência.';
    // Em produção, você logaria o erro: error_log($e->getMessage());
    echo json_encode($response);
}
?>