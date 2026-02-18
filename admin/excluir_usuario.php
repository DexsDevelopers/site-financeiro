<?php
// /admin/excluir_usuario.php (Versão Moderna e Segura com AJAX)

session_start();
header('Content-Type: application/json');

// Resposta padrão de erro
$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

// --- 1. VERIFICAÇÃO DE SEGURANÇA: O usuário logado é um admin? ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    $response['message'] = 'Acesso negado. Sessão não encontrada.';
    echo json_encode($response);
    exit();
}

require_once '../includes/db_connect.php';

try {
    $stmt_check = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    $user_tipo = $stmt_check->fetchColumn();

    if ($user_tipo !== 'admin') {
        http_response_code(403); // Proibido
        $response['message'] = 'Acesso negado. Você não tem permissão de administrador.';
        echo json_encode($response);
        exit();
    }
} catch (PDOException $e) {
    http_response_code(500); // Erro de Servidor
    $response['message'] = 'Erro ao verificar permissões do usuário.';
    echo json_encode($response);
    exit();
}

// --- 2. RECEBER DADOS E VALIDAR ---
// Usando POST com corpo JSON para mais segurança que GET
$input = json_decode(file_get_contents('php://input'), true);
$userIdToDelete = $input['id'] ?? 0;

if (empty($userIdToDelete) || !is_numeric($userIdToDelete)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'ID do usuário a ser excluído não foi fornecido ou é inválido.';
    echo json_encode($response);
    exit();
}

// --- 3. VERIFICAÇÃO DE SEGURANÇA: Impede que um admin se auto-exclua ---
if ($userIdToDelete == $_SESSION['user_id']) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'Você não pode excluir sua própria conta de administrador a partir deste painel.';
    echo json_encode($response);
    exit();
}

// --- 4. EXCLUSÃO DO BANCO DE DADOS ---
try {
    // A query já tem a segurança de apenas deletar 'usuario', o que é bom.
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND tipo = 'usuario'");
    $stmt->execute([$userIdToDelete]);

    // rowCount() verifica se alguma linha foi realmente afetada/deletada.
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Usuário excluído com sucesso!';
    } else {
        // Isso pode acontecer se o ID não existir ou se o tipo não for 'usuario' (ex: tentar excluir outro admin)
        http_response_code(404); // Não Encontrado
        $response['message'] = 'Usuário não encontrado ou não pôde ser excluído (não é um usuário comum).';
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao excluir o usuário.';
    echo json_encode($response);
}
?>