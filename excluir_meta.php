<?php
// /excluir_meta.php (Versão Moderna com AJAX)

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
$input = json_decode(file_get_contents('php://input'), true);
$metaId = $input['id'] ?? 0;
$userId = $_SESSION['user_id'];

if (empty($metaId) || !is_numeric($metaId)) {
    http_response_code(400); // Requisição Inválida
    $response['message'] = 'ID da meta é inválido.';
    echo json_encode($response);
    exit();
}

// --- 3. Exclusão Segura no Banco ---
try {
    // Apenas o dono da meta pode excluí-la
    $stmt = $pdo->prepare("DELETE FROM metas_compra WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$metaId, $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Meta excluída com sucesso!';
    } else {
        http_response_code(404); // Não Encontrado
        $response['message'] = 'Meta não encontrada ou você не tem permissão para excluí-la.';
    }
    
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500); // Erro Interno do Servidor
    $response['message'] = 'Erro no banco de dados ao excluir a meta.';
    // error_log($e->getMessage()); // Em produção
    echo json_encode($response);
}
?>