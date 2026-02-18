<?php
// buscar_estatisticas_alimentacao.php - Buscar estatísticas de alimentação do dia

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

try {
    $sql = "SELECT 
                COUNT(*) as total_refeicoes,
                COALESCE(SUM(calorias), 0) as total_calorias,
                COALESCE(AVG(calorias), 0) as media_calorias
            FROM registros_alimentacao 
            WHERE id_usuario = ? AND data_refeicao = CURDATE()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['total_refeicoes'] = (int)$stats['total_refeicoes'];
    $response['total_calorias'] = (int)$stats['total_calorias'];
    $response['media_calorias'] = round((float)$stats['media_calorias']);
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>

