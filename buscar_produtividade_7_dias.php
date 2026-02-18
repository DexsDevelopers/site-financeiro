<?php
// buscar_produtividade_7_dias.php - Buscar dados de produtividade dos últimos 7 dias

// Limpar qualquer output anterior
ob_clean();

session_start();

// Headers corretos para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

$response = ['success' => false, 'message' => 'Erro inesperado.'];

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = 'Acesso negado.';
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        echo '{"success":false,"message":"Erro ao gerar JSON"}';
    } else {
        echo $json;
    }
    exit();
}

try {
    require_once 'includes/db_connect.php';
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro de conexão: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if (!$stmt_check->fetch()) {
        $response['message'] = 'Tabela tarefas não encontrada.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Buscar tarefas concluídas dos últimos 7 dias
    $stmt = $pdo->prepare("
        SELECT 
            DATE(data_conclusao) as data,
            COUNT(*) as total_concluidas
        FROM tarefas 
        WHERE id_usuario = ? 
        AND status = 'concluida'
        AND data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(data_conclusao)
        ORDER BY data ASC
    ");
    $stmt->execute([$userId]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Criar array com os últimos 7 dias
    $labels = [];
    $tarefas = [];
    $totalGeral = 0;
    
    for ($i = 6; $i >= 0; $i--) {
        $data = date('Y-m-d', strtotime("-{$i} days"));
        $dataFormatada = date('d/m', strtotime($data));
        $labels[] = $dataFormatada;
        
        // Buscar total de tarefas concluídas neste dia
        $total = 0;
        foreach ($resultados as $resultado) {
            if ($resultado['data'] === $data) {
                $total = (int)$resultado['total_concluidas'];
                break;
            }
        }
        $tarefas[] = $total;
        $totalGeral += $total;
    }
    
    $response['success'] = true;
    $response['labels'] = $labels;
    $response['tarefas'] = $tarefas;
    $response['total_geral'] = $totalGeral;
    $response['media_diaria'] = round($totalGeral / 7, 2);
    
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        $response['success'] = false;
        $response['message'] = 'Erro ao gerar JSON: ' . json_last_error_msg();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        echo $json;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Erro geral: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>
