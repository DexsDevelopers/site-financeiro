<?php
// processar_analise_ia.php - Backend de Análise Inteligente Local (Orion Engine)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/includes/db_connect.php';
    require_once __DIR__ . '/includes/OrionEngine.php';
    
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Usuário não autenticado");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $pergunta = trim($input['pergunta'] ?? '');
    $userId = $_SESSION['user_id'];

    if (empty($pergunta)) {
        throw new Exception("Pergunta não fornecida");
    }

    // Inicializa o Motor Analítico Orion
    $orion = new OrionEngine($pdo, $userId);
    
    // Processa a pergunta usando lógica local robusta
    $resposta = $orion->processQuery($pergunta);

    // Adiciona um pequeno delay artificial para simular "pensamento" (UX)
    usleep(800000); 

    echo json_encode([
        'success' => true, 
        'resposta' => $resposta,
        'engine' => 'Orion v2.0 (Local)'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no Orion Engine: ' . $e->getMessage()
    ]);
}
?>