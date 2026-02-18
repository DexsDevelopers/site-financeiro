<?php
// /atualizar_perfil.php (Versão Moderna com AJAX)

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// 1. Verificações de Acesso e Método
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

// 2. Coleta e Validação dos Dados
$userId = $_SESSION['user_id'];
$nome_completo = trim($_POST['nome_completo'] ?? '');

if (empty($nome_completo) || strlen($nome_completo) < 3) {
    http_response_code(400);
    $response['message'] = 'O nome completo é obrigatório e deve ter no mínimo 3 caracteres.';
    echo json_encode($response);
    exit();
}

try {
    // 3. Atualização Segura no Banco de Dados
    $stmt = $pdo->prepare("UPDATE usuarios SET nome_completo = ? WHERE id = ?");
    $stmt->execute([$nome_completo, $userId]);

    // 4. Atualiza o nome na sessão para refletir a mudança em toda a aplicação
    $_SESSION['user_name'] = $nome_completo;

    // 5. Resposta de Sucesso
    $response['success'] = true;
    $response['message'] = 'Nome atualizado com sucesso!';
    // Retorna o novo nome para o frontend
    $response['novo_nome'] = $nome_completo;
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar o perfil.';
    // error_log($e->getMessage()); // Em produção
    echo json_encode($response);
}
?>