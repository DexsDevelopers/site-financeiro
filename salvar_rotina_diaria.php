<?php
// salvar_rotina_diaria.php - Salvar status da rotina diária

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Erro inesperado.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    echo json_encode($response);
    exit();
}

require_once 'includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$habitId = $input['id'] ?? 0;
$status = $input['status'] ?? 'pendente';
$userId = $_SESSION['user_id'];

if (empty($habitId) || !in_array($status, ['pendente', 'concluido'])) {
    http_response_code(400);
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit();
}

try {
    // Verificar se a rotina pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM rotina_diaria WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$habitId, $userId]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        $response['message'] = 'Rotina não encontrada.';
        echo json_encode($response);
        exit();
    }
    
    // Atualizar status
    $stmt = $pdo->prepare("UPDATE rotina_diaria SET status = ?, horario = ? WHERE id = ? AND id_usuario = ?");
    $horario = $status === 'concluido' ? date('H:i:s') : null;
    $stmt->execute([$status, $horario, $habitId, $userId]);
    
    $response['success'] = true;
    $response['message'] = 'Status atualizado com sucesso.';
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>
