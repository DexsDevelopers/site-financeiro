<?php
// /atualizar_senha.php (Versão Moderna com AJAX)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. Verificações de Acesso e Método ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
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

// --- 2. Coleta e Validação dos Dados ---
$userId = $_SESSION['user_id'];
$senha_atual = $_POST['senha_atual'] ?? '';
$nova_senha = $_POST['nova_senha'] ?? '';
$confirmar_nova_senha = $_POST['confirmar_nova_senha'] ?? '';

if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_nova_senha)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Todos os campos de senha são obrigatórios.';
    echo json_encode($response);
    exit();
}

if (strlen($nova_senha) < 8) { // Regra de negócio: senha com no mínimo 8 caracteres
    http_response_code(400);
    $response['message'] = 'A nova senha deve ter no mínimo 8 caracteres.';
    echo json_encode($response);
    exit();
}

if ($nova_senha !== $confirmar_nova_senha) {
    http_response_code(400);
    $response['message'] = 'A nova senha e a confirmação não correspondem.';
    echo json_encode($response);
    exit();
}

// --- 3. Lógica de Atualização Segura ---
try {
    // Busca a senha atual (hash) do usuário no banco de dados
    $stmt_check = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
    $stmt_check->execute([$userId]);
    $user = $stmt_check->fetch();

    // Verifica se a senha atual fornecida bate com a que está no banco
    if (!$user || !password_verify($senha_atual, $user['senha_hash'])) {
        http_response_code(401); // Não Autorizado
        $response['message'] = 'A senha atual está incorreta.';
        echo json_encode($response);
        exit();
    }

    // Se tudo estiver correto, criptografa a nova senha
    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

    // Atualiza a nova senha no banco de dados
    $stmt_update = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
    $stmt_update->execute([$nova_senha_hash, $userId]);

    // Resposta de Sucesso
    $response['success'] = true;
    $response['message'] = 'Sua senha foi alterada com sucesso!';
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar a senha.';
    // Em produção: error_log($e->getMessage());
    echo json_encode($response);
}
?>