<?php
/**
 * admin_bot_ia.php - Endpoint de IA para WhatsApp Bot
 * Versão auto-contida com queries SQL diretas (sem dependências externas)
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(120);

// Handler de erros fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'resposta' => 'Erro fatal: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

try {
    // 1. Carregar apenas db_connect.php
    require_once 'includes/db_connect.php';
    
    // Verificar conexão com banco
    if (!isset($pdo) || $pdo === null) {
        throw new Exception('Banco de dados não conectado');
    }
    
    // 2. Verificar API Key do Gemini
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        throw new Exception('API Key do Gemini não configurada. Configure o arquivo .env com GEMINI_API_KEY');
    }
    
    // 3. Validar token de autenticação
    $headers = getallheaders();
    if ($headers === false) {
        $headers = [];
    }
    $token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    
    // Carregar config.json para token
    $config = [];
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);
        $config = json_decode($configContent, true) ?? [];
    }
    $expectedToken = $config['WHATSAPP_API_TOKEN'] ?? 'site-financeiro-token-2024';
    
    if ($token !== $expectedToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'resposta' => 'Token inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 4. Obter dados da requisição
    $inputRaw = file_get_contents('php://input');
    if ($inputRaw === false) {
        throw new Exception('Erro ao ler dados da requisição');
    }
    $input = json_decode($inputRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
    }
    
    $pergunta = trim($input['pergunta'] ?? '');
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : null;
    
    if (empty($pergunta) || !$userId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'resposta' => 'Pergunta e user_id são obrigatórios'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 5. Validar que o usuário existe
    $stmt = $pdo->prepare("SELECT id, nome_completo FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'resposta' => 'Usuário inválido. Faça login novamente com !login'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 6. Buscar dados financeiros do usuário (queries SQL diretas)
    $mesAtual = (int)date('m');
    $anoAtual = (int)date('Y');
    
    // Receitas do mês
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(valor), 0) as total, COUNT(*) as count 
        FROM transacoes 
        WHERE id_usuario = ? 
        AND tipo = 'receita' 
        AND YEAR(data_transacao) = ? 
        AND MONTH(data_transacao) = ?
    ");
    $stmt->execute([$userId, $anoAtual, $mesAtual]);
    $receitas = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalReceitas = (float)($receitas['total'] ?? 0);
    $countReceitas = (int)($receitas['count'] ?? 0);
    
    // Despesas do mês
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(valor), 0) as total, COUNT(*) as count 
        FROM transacoes 
        WHERE id_usuario = ? 
        AND tipo = 'despesa' 
        AND YEAR(data_transacao) = ? 
        AND MONTH(data_transacao) = ?
    ");
    $stmt->execute([$userId, $anoAtual, $mesAtual]);
    $despesas = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalDespesas = (float)($despesas['total'] ?? 0);
    $countDespesas = (int)($despesas['count'] ?? 0);
    
    $saldo = $totalReceitas - $totalDespesas;
    
    // 7. Buscar top 5 tarefas urgentes (queries SQL diretas)
    $stmt = $pdo->prepare("
        SELECT id, descricao, prioridade, data_limite,
            CASE 
                WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 'Vencida'
                WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'Urgente'
                WHEN prioridade = 'Alta' THEN 'Alta Prioridade'
                ELSE 'Normal'
            END as status_urgencia
        FROM tarefas 
        WHERE id_usuario = ? 
        AND status = 'pendente' 
        AND (
            prioridade = 'Alta' 
            OR (data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
        )
        ORDER BY 
            CASE WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 1 ELSE 2 END,
            CASE WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 2 END,
            FIELD(prioridade, 'Alta', 'Média', 'Baixa'),
            data_limite ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $tarefasUrgentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar tarefas para contexto
    $tarefasTexto = '';
    if (!empty($tarefasUrgentes)) {
        foreach ($tarefasUrgentes as $t) {
            $dataInfo = '';
            if (!empty($t['data_limite'])) {
                $dataFormatada = date('d/m/Y', strtotime($t['data_limite']));
                $dataInfo = " (Prazo: {$dataFormatada})";
            }
            $tarefasTexto .= "- {$t['descricao']} - Prioridade: {$t['prioridade']}{$dataInfo}\n";
        }
    } else {
        $tarefasTexto = "Nenhuma tarefa urgente no momento.";
    }
    
    // 8. Montar contexto do sistema com os dados
    $systemContext = "CONTEXTO DO USUÁRIO:\n\n";
    $systemContext .= "RESUMO FINANCEIRO DO MÊS ({$mesAtual}/{$anoAtual}):\n";
    $systemContext .= "- Receitas: R$ " . number_format($totalReceitas, 2, ',', '.') . " ({$countReceitas} transações)\n";
    $systemContext .= "- Despesas: R$ " . number_format($totalDespesas, 2, ',', '.') . " ({$countDespesas} transações)\n";
    $systemContext .= "- Saldo: R$ " . number_format($saldo, 2, ',', '.') . "\n\n";
    $systemContext .= "TAREFAS URGENTES (Top 5):\n{$tarefasTexto}\n";
    
    // 9. Chamar API Gemini
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . GEMINI_API_KEY;
    
    $prompt = "Você é um assistente financeiro especializado em ajudar usuários a gerenciar suas finanças e tarefas através do WhatsApp.\n\n";
    $prompt .= $systemContext . "\n";
    $prompt .= "PERGUNTA DO USUÁRIO: {$pergunta}\n\n";
    $prompt .= "Responda de forma clara, concisa e objetiva. Use os dados do contexto acima para responder. Formate números monetários em R$ e datas em formato brasileiro (dd/mm/aaaa).";
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("Erro cURL: $curlError");
    }
    
    if ($httpCode !== 200) {
        $errorDetails = '';
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['error']['message'])) {
                $errorDetails = ': ' . $errorData['error']['message'];
            } else {
                $errorDetails = ': ' . substr($response, 0, 200);
            }
        }
        throw new Exception("Erro HTTP $httpCode da API Gemini$errorDetails");
    }
    
    $apiResponse = json_decode($response, true);
    if (!$apiResponse || !isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Resposta inválida da API Gemini");
    }
    
    $respostaFinal = $apiResponse['candidates'][0]['content']['parts'][0]['text'];
    
    // 10. Retornar resposta
    echo json_encode([
        'success' => true,
        'resposta' => $respostaFinal
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("[BOT_IA] Erro: " . $e->getMessage());
    error_log("[BOT_IA] Stack: " . ($e->getTraceAsString() ?? ''));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'resposta' => 'Erro: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
