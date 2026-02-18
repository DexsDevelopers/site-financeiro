<?php
// editar_refeicao.php - Editar uma refeição existente

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
$tipo_refeicao = $_POST['tipo_refeicao'] ?? '';
$descricao = trim($_POST['descricao'] ?? '');
$calorias = !empty($_POST['calorias']) ? (int)$_POST['calorias'] : null;

if ($refeicaoId <= 0) {
    http_response_code(400);
    $response['message'] = 'ID da refeição inválido.';
    echo json_encode($response);
    exit();
}

if (empty($tipo_refeicao) || empty($descricao)) {
    http_response_code(400);
    $response['message'] = 'O tipo e a descrição da refeição são obrigatórios.';
    echo json_encode($response);
    exit();
}

try {
    // Verificar se a refeição pertence ao usuário
    $stmt_check = $pdo->prepare("SELECT id FROM registros_alimentacao WHERE id = ? AND id_usuario = ?");
    $stmt_check->execute([$refeicaoId, $userId]);
    
    if ($stmt_check->rowCount() == 0) {
        http_response_code(403);
        $response['message'] = 'Refeição não encontrada ou você não tem permissão para editá-la.';
        echo json_encode($response);
        exit();
    }
    
    // Atualizar a refeição
    $sql = "UPDATE registros_alimentacao 
            SET tipo_refeicao = ?, descricao = ?, calorias = ? 
            WHERE id = ? AND id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tipo_refeicao, $descricao, $calorias, $refeicaoId, $userId]);
    
    $response['success'] = true;
    $response['message'] = 'Refeição atualizada com sucesso!';
    $response['refeicao'] = [
        'id' => $refeicaoId,
        'descricao' => $descricao,
        'calorias' => $calorias,
        'tipo_refeicao' => $tipo_refeicao
    ];
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados ao atualizar a refeição.';
    echo json_encode($response);
}
?>

