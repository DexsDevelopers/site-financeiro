<?php
// /admin/redefinir_senha.php (100% Completo)

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro.'];

// Segurança: Apenas um admin logado pode executar esta ação
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once '../includes/db_connect.php';

try {
    $stmt_check = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    if ($stmt_check->fetchColumn() !== 'admin') {
        http_response_code(403);
        $response['message'] = 'Você não tem permissão de administrador.';
        echo json_encode($response);
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro de permissão.';
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$userIdToReset = $input['id'] ?? 0;

if (empty($userIdToReset)) {
    http_response_code(400);
    $response['message'] = 'ID do usuário não fornecido.';
    echo json_encode($response);
    exit();
}

// Gera uma nova senha aleatória e segura de 12 caracteres
$nova_senha = bin2hex(random_bytes(6));

// Criptografa a nova senha
$nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

try {
    // Atualiza a senha no banco de dados
    $stmt_update = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ? AND tipo = 'usuario'");
    $stmt_update->execute([$nova_senha_hash, $userIdToReset]);
    
    if ($stmt_update->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Senha redefinida com sucesso.';
        $response['nova_senha'] = $nova_senha; // Retorna a senha em texto plano para o admin
    } else {
        http_response_code(404);
        $response['message'] = 'Usuário não encontrado ou não é um usuário comum.';
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao redefinir a senha.';
    echo json_encode($response);
}
?>