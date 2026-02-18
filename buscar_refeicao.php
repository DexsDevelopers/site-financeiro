<?php
// buscar_refeicao.php - Buscar uma refeição específica para edição

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
$refeicaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($refeicaoId <= 0) {
    http_response_code(400);
    $response['message'] = 'ID da refeição inválido.';
    echo json_encode($response);
    exit();
}

try {
    $sql = "SELECT id, tipo_refeicao, descricao, calorias 
            FROM registros_alimentacao 
            WHERE id = ? AND id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$refeicaoId, $userId]);
    $refeicao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($refeicao) {
        $response['success'] = true;
        $response['refeicao'] = $refeicao;
    } else {
        http_response_code(404);
        $response['message'] = 'Refeição não encontrada.';
    }
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao buscar a refeição.';
    echo json_encode($response);
}
?>

