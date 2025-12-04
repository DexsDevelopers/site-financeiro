<?php
// processar_analise_ia.php - Backend de Debug/Teste do Gemini
// Versão Final: API Estável + Correção de Tabelas

// 1. Configurações e INCLUDES OBRIGATÓRIOS
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // Carregamento forçado das dependências
    if (!file_exists(__DIR__ . '/includes/db_connect.php')) throw new Exception("db_connect.php não encontrado");
    require_once __DIR__ . '/includes/db_connect.php';
    
    // Tenta carregar os helpers
    if (file_exists(__DIR__ . '/includes/finance_helper.php')) require_once __DIR__ . '/includes/finance_helper.php';
    if (file_exists(__DIR__ . '/includes/tasks_helper.php')) require_once __DIR__ . '/includes/tasks_helper.php';
    if (file_exists(__DIR__ . '/includes/rate_limiter.php')) require_once __DIR__ . '/includes/rate_limiter.php';

    // 2. Iniciar sessão e receber dados
    session_start();
    $input = json_decode(file_get_contents('php://input'), true);
    $pergunta = $input['pergunta'] ?? 'Faça uma análise geral';
    $userId = $_SESSION['user_id'] ?? $input['user_id'] ?? 87;

    // 3. Validação da Chave
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        throw new Exception("GEMINI_API_KEY não configurada.");
    }

    // 4. Verificação de Rate Limit
    if (class_exists('RateLimiter')) {
        try {
            $limiter = new RateLimiter($pdo);
            if (method_exists($limiter, 'checkRateLimit')) {
                $rateCheck = $limiter->checkRateLimit($userId, 'gemini');
                if (!$rateCheck['allowed']) {
                    throw new Exception($rateCheck['message'] ?? "Rate limit atingido. Aguarde alguns segundos.");
                }
            }
        } catch (Exception $e) {
            error_log("Rate Limiter Error: " . $e->getMessage());
        }
    }

    // 5. Coletar Dados (Usando nomes de tabelas em INGLÊS conforme padrão do sistema)
    
    // Finanças (transactions)
    $stmt = $pdo->prepare("SELECT SUM(value) as total FROM transactions WHERE type='receita' AND id_usuario=?");
    $stmt->execute([$userId]);
    $rec = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->prepare("SELECT SUM(value) as total FROM transactions WHERE type='despesa' AND id_usuario=?");
    $stmt->execute([$userId]);
    $desp = $stmt->fetchColumn() ?: 0;
    
    $saldo = $rec - $desp;

    // Tarefas (tasks)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id_usuario=? AND status='pendente'");
    $stmt->execute([$userId]);
    $tarefasPendentes = $stmt->fetchColumn();

    // Contexto
    $contexto = "Usuário: Lucas.\nDados Financeiros:\nReceitas: R$ $rec\nDespesas: R$ $desp\nSaldo: R$ $saldo\nTarefas Pendentes: $tarefasPendentes";

    // 6. Chamada API (Usando gemini-2.5-flash - Recomendado)
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        "contents" => [
            ["parts" => [["text" => "Você é um assistente financeiro. Analise estes dados brevemente:\n$contexto\n\nPergunta: $pergunta"]]]
        ],
        "generationConfig" => [
            "temperature" => 0.3,
            "maxOutputTokens" => 300
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Erro API Gemini ($httpCode): $response");
    }

    $data = json_decode($response, true);
    $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta da IA';

    echo json_encode(['success' => true, 'resposta' => $texto]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>