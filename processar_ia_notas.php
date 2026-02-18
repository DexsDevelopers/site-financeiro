<?php
// processar_ia_notas.php - Processamento de IA para Notas e Mapas Mentais
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

if (!defined('GEMINI_API_KEY')) {
    if (file_exists('includes/config.php')) require_once 'includes/config.php';
    if (!defined('GEMINI_API_KEY')) {
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'API Key não configurada.']));
    }
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? ''; // 'resumir' ou 'gerar_mapa'
$notaId = isset($input['nota_id']) ? (int)$input['nota_id'] : null;

if (!$notaId || !$acao) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']));
}

// Buscar nota
$stmt = $pdo->prepare("SELECT titulo, conteudo FROM notas_cursos WHERE id = ? AND id_usuario = ?");
$stmt->execute([$notaId, $userId]);
$nota = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nota) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Nota não encontrada.']));
}

function callGeminiAPI($prompt) {
    $api_key = GEMINI_API_KEY;
    $model = 'gemini-1.5-flash'; // Flash é ideal para resumos rápidos
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
    
    $data = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.4]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return json_decode($response, true);
    }
    return null;
}

if ($acao === 'resumir') {
    $prompt = "Resuma a seguinte anotação de curso de forma concisa e estruturada em tópicos (bullet points). Use tom profissional. \n\nTítulo: {$nota['titulo']}\nConteúdo: {$nota['conteudo']}";
    $response = callGeminiAPI($prompt);
    
    if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        $resumo = $response['candidates'][0]['content']['parts'][0]['text'];
        echo json_encode(['success' => true, 'resumo' => $resumo]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar resumo pela IA.']);
    }
} elseif ($acao === 'gerar_mapa') {
    $prompt = "Com base nesta anotação de curso, crie um mapa mental em formato JSON estrito.\n\nRegras:\n1. O nó central deve ser o título: '{$nota['titulo']}'.\n2. Crie nós secundários para os principais conceitos.\n3. Retorne APENAS o JSON no formato: {\"nodes\": [{\"id\": 1, \"text\": \"Título\", \"isCentral\": true}, {\"id\": 2, \"text\": \"Conceito 1\"}], \"edges\": [{\"from\": 1, \"to\": 2}]}.\n4. Limite a 10 nós no total.\n\nConteúdo: {$nota['conteudo']}";
    $response = callGeminiAPI($prompt);
    
    if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        $raw_text = $response['candidates'][0]['content']['parts'][0]['text'];
        preg_match('/\{[\s\S]*\}/', $raw_text, $matches);
        $json_string = $matches[0] ?? '';
        
        $dados_mapa = json_decode($json_string, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode(['success' => true, 'mapa' => $dados_mapa]);
        } else {
            echo json_encode(['success' => false, 'message' => 'IA gerou um formato inválido.', 'raw' => $raw_text]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar mapa pela IA.']);
    }
}
?>
