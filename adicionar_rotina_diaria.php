<?php
// adicionar_rotina_diaria.php - Adicionar novo hábito à rotina diária

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
$nome = trim($input['nome'] ?? '');
$userId = $_SESSION['user_id'];
$dataHoje = date('Y-m-d');

if (empty($nome)) {
    http_response_code(400);
    $response['message'] = 'Nome do hábito é obrigatório.';
    echo json_encode($response);
    exit();
}

try {
    // Verificar se já existe para hoje
    $stmt = $pdo->prepare("SELECT id FROM rotina_diaria WHERE id_usuario = ? AND nome = ? AND data_execucao = ?");
    $stmt->execute([$userId, $nome, $dataHoje]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        $response['message'] = 'Este hábito já existe para hoje.';
        echo json_encode($response);
        exit();
    }
    
    // Adicionar à rotina diária
    $stmt = $pdo->prepare("INSERT INTO rotina_diaria (id_usuario, nome, data_execucao, status) VALUES (?, ?, ?, 'pendente')");
    $stmt->execute([$userId, $nome, $dataHoje]);
    
    // Adicionar à configuração padrão se não existir
    $stmt = $pdo->prepare("SELECT id FROM config_rotina_padrao WHERE id_usuario = ? AND nome = ?");
    $stmt->execute([$userId, $nome]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO config_rotina_padrao (id_usuario, nome, ativo) VALUES (?, ?, TRUE)");
        $stmt->execute([$userId, $nome]);
    }
    
    $response['success'] = true;
    $response['message'] = 'Hábito adicionado com sucesso.';
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados.';
    echo json_encode($response);
}
?>
