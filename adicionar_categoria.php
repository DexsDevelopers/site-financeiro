<?php
// /adicionar_categoria.php (Versão Moderna com AJAX e Validação)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Faça o login novamente.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$nome = trim($_POST['nome'] ?? '');
$tipo = $_POST['tipo'] ?? '';

// 1. Validação de Dados de Entrada
if (empty($nome) || !in_array($tipo, ['receita', 'despesa'])) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'O nome e o tipo da categoria são obrigatórios.';
    echo json_encode($response);
    exit();
}

try {
    // 2. Validação Profissional: Verifica se já existe uma categoria com o mesmo nome e tipo
    $stmt_check = $pdo->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND nome = ? AND tipo = ?");
    $stmt_check->execute([$userId, $nome, $tipo]);
    if ($stmt_check->fetch()) {
        http_response_code(409); // Conflito
        $response['message'] = 'Você já possui uma categoria com este nome e tipo.';
        echo json_encode($response);
        exit();
    }

    // 3. Inserção no Banco de Dados
    $stmt_insert = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
    $stmt_insert->execute([$userId, $nome, $tipo]);
    
    // Pega o ID da categoria que acabamos de criar
    $newCategoryId = $pdo->lastInsertId();

    // 4. Resposta de Sucesso
    $response['success'] = true;
    $response['message'] = 'Categoria "' . htmlspecialchars($nome) . '" criada com sucesso!';
    // Retorna os dados da nova categoria para o frontend poder adicioná-la na lista dinamicamente
    $response['categoria'] = [
        'id'   => $newCategoryId,
        'nome' => $nome,
        'tipo' => $tipo
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao salvar a categoria.';
    // Em produção, você logaria o erro em um arquivo: error_log($e->getMessage());
    echo json_encode($response);
}
?>