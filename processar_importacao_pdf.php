<?php
// processar_importacao_pdf.php - Processar importação de transações do PDF

session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não logado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido.';
    echo json_encode($response);
    exit;
}

require_once 'includes/db_connect.php';
require_once 'includes/pdfjs_processor.php';

$userId = $_SESSION['user_id'];
$transacoes = json_decode($_POST['transacoes'] ?? '[]', true);

if (empty($transacoes)) {
    $response['message'] = 'Nenhuma transação para importar.';
    echo json_encode($response);
    exit;
}

try {
    $processor = new PDFJSProcessor($pdo, $userId);
    $resultado = $processor->salvarTransacoes($transacoes);
    
    $response['success'] = true;
    $response['message'] = "Importação concluída! {$resultado['salvas']} transações importadas, {$resultado['duplicadas']} duplicadas ignoradas.";
    $response['dados'] = $resultado;
    
} catch (Exception $e) {
    $response['message'] = 'Erro ao importar transações: ' . $e->getMessage();
}

echo json_encode($response);
?>
