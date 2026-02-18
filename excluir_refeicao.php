<?php
// excluir_refeicao.php - Excluir uma refeição

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocorreu um erro inesperado.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'];
$refeicaoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($refeicaoId <= 0) {
    http_response_code(400);
    $response['message'] = 'ID da refeição inválido.';
    echo json_encode($response);
    exit();
}

try {
    // Verificar se a refeição pertence ao usuário
    $stmt_check = $pdo->prepare("SELECT tipo_refeicao FROM registros_alimentacao WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$refeicaoId, $userId]);
    $refeicao = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$refeicao) {
        http_response_code(403);
        $response['message'] = 'Refeição não encontrada ou você não tem permissão para excluí-la.';
        echo json_encode($response);
        exit();
    }
    
    // Excluir a refeição
    $sql = "DELETE FROM registros_alimentacao WHERE id = ? AND id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$refeicaoId, $userId]);
    
    $response['success'] = true;
    $response['message'] = 'Refeição excluída com sucesso!';
    $response['tipo_refeicao'] = $refeicao['tipo_refeicao'];
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao excluir a refeição.';
    echo json_encode($response);
}
?>

