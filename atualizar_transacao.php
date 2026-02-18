<?php
// /atualizar_transacao.php (Versão Moderna com AJAX e Categorias)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. Verificações de Acesso e Método ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Método de requisição inválido.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

// --- 2. Coleta e Validação dos Dados ---
$userId = $_SESSION['user_id'];
$transacaoId = $_POST['id'] ?? 0;
$descricao = trim($_POST['descricao'] ?? '');
$valor = $_POST['valor'] ?? null;
$id_categoria = $_POST['id_categoria'] ?? null; // Alterado de 'tipo' para 'id_categoria'
$data_transacao = $_POST['data_transacao'] ?? '';

if (empty($transacaoId) || empty($descricao) || !is_numeric($valor) || $valor < 0 || empty($id_categoria) || empty($data_transacao)) {
    http_response_code(400);
    $response['message'] = 'Todos os campos são obrigatórios e os valores devem ser válidos.';
    echo json_encode($response);
    exit();
}

try {
    // --- 3. VERIFICA INTEGRIDADE: Categoria pertence ao usuário? ---
    // Busca o tipo ('receita' ou 'despesa') a partir da categoria selecionada
    $stmt_cat = $pdo->prepare("SELECT tipo FROM categorias WHERE id = ? AND id_usuario = ?");
    $stmt_cat->execute([$id_categoria, $userId]);
    $tipo = $stmt_cat->fetchColumn();

    if (!$tipo) {
        http_response_code(403); // Proibido, pois a categoria não pertence ao usuário ou não existe
        $response['message'] = 'Categoria inválida ou não pertence a você.';
        echo json_encode($response);
        exit();
    }

    // --- 4. ATUALIZAÇÃO SEGURA NO BANCO ---
    $sql = "UPDATE transacoes 
            SET descricao = ?, valor = ?, tipo = ?, data_transacao = ?, id_categoria = ?
            WHERE id = ? AND id_usuario = ?";
    $stmt_update = $pdo->prepare($sql);
    $stmt_update->execute([$descricao, $valor, $tipo, $data_transacao, $id_categoria, $transacaoId, $userId]);

    // Não é necessário verificar o rowCount(), pois o usuário pode salvar sem fazer alterações.
    // A verificação de segurança no WHERE já garante que ele só edite o que é dele.
    $response['success'] = true;
    $response['message'] = 'Transação atualizada com sucesso!';
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar a transação.';
    // error_log($e->getMessage());
    echo json_encode($response);
}
?>