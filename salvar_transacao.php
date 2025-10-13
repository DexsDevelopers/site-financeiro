<?php
// /salvar_transacao.php (Versão Profissional Refinada com Retorno de Dados)

date_default_timezone_set('America/Sao_Paulo');

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. Verificações Iniciais (Fail Fast) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método não permitido
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Faça o login novamente.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- 2. Coleta e Validação de Dados ---
$id_usuario = $_SESSION['user_id'];
$descricao = trim($_POST['descricao'] ?? '');
$valor = $_POST['valor'] ?? '';
$id_categoria = $_POST['id_categoria'] ?? '';
$data_transacao = $_POST['data_transacao'] ?? '';

if (empty($descricao) || !is_numeric($valor) || $valor < 0 || empty($id_categoria) || empty($data_transacao)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Todos os campos são obrigatórios e os valores devem ser válidos.';
    echo json_encode($response);
    exit();
}

try {
    // --- 3. Verificação de Integridade da Categoria ---
    // Busca o nome e o tipo da categoria selecionada para garantir consistência e retornar ao frontend
    $stmt_tipo = $pdo->prepare("SELECT nome, tipo FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt_tipo->execute([$id_categoria, $id_usuario]);
    $categoria_info = $stmt_tipo->fetch(PDO::FETCH_ASSOC);

    if (!$categoria_info) {
        http_response_code(400); // Requisição Inválida (ou 403, Proibido)
        $response['message'] = 'Categoria selecionada é inválida ou não pertence a você.';
        echo json_encode($response);
        exit();
    }
    $tipo = $categoria_info['tipo'];

    // --- 4. Inserção no Banco de Dados ---
    $sql = "INSERT INTO transacoes (id_usuario, id_categoria, descricao, valor, tipo, data_transacao) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario, $id_categoria, $descricao, $valor, $tipo, $data_transacao]);
    
    $newTransactionId = $pdo->lastInsertId();

    // --- 5. Resposta de Sucesso com Dados Completos ---
    $response['success'] = true;
    $response['message'] = 'Lançamento salvo com sucesso!';
    // Retorna os dados do novo lançamento para o frontend
    $response['transacao'] = [
        'id'             => $newTransactionId,
        'descricao'      => $descricao,
        'valor'          => (float)$valor,
        'tipo'           => $tipo,
        'data_transacao' => $data_transacao,
        'nome_categoria' => $categoria_info['nome']
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao salvar o lançamento.';
    // error_log($e->getMessage());
    echo json_encode($response);
}
?>