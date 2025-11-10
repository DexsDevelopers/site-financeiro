<?php
// /processar_ia.php (Versão com Criação Inteligente de Categoria)

session_start();
header('Content-Type: application/json');

require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

$input = json_decode(file_get_contents('php://input'), true);
$texto_usuario = $input['texto'] ?? '';
$id_conta_req = isset($input['id_conta']) ? (int)$input['id_conta'] : null;
$userId = $_SESSION['user_id'];

if (empty($texto_usuario)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Nenhum texto fornecido.']));
}

// Verificar rate limiting interno (pode ser desabilitado temporariamente para debug)
// IMPORTANTE: Se você está tendo problemas mesmo com a API funcionando,
// pode ser que o rate limiting interno esteja bloqueando. 
// Para desabilitar temporariamente, altere a linha abaixo para: $enableInternalRateLimit = false;
$enableInternalRateLimit = true; // Altere para false para desabilitar rate limiting interno

$rateLimiter = null;
if ($enableInternalRateLimit) {
    try {
        $rateLimiter = new RateLimiter($pdo);
        $rateLimitCheck = $rateLimiter->checkRateLimit($userId, 'gemini');

        if (!$rateLimitCheck['allowed']) {
            http_response_code(429);
            exit(json_encode([
                'success' => false,
                'message' => $rateLimitCheck['message'] . ' (Limite interno do sistema - não é da API)',
                'retry_after' => $rateLimitCheck['retry_after'],
                'limit_type' => $rateLimitCheck['limit_type'],
                'rate_limit_info' => $rateLimiter->getUsageStats($userId, 'gemini'),
                'internal_rate_limit' => true,
                'note' => 'Este é o rate limiting interno. Se a API não está no limite, você pode desabilitar temporariamente em processar_ia.php'
            ]));
        }
    } catch (Exception $e) {
        // Se houver erro no rate limiter, continua sem rate limiting (modo degradado)
        error_log("Rate Limiter Error: " . $e->getMessage());
        // Continua com a requisição normalmente
    }
}

// --- COLETA DE DADOS PARA CONTEXTO DA IA ---
try {
    $stmt_cats = $pdo->prepare("SELECT nome, tipo FROM categorias WHERE id_usuario = ?");
    $stmt_cats->execute([$userId]);
    $categorias_usuario = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
    
    $categorias_despesa = [];
    $categorias_receita = [];
    foreach ($categorias_usuario as $cat) {
        if ($cat['tipo'] === 'despesa') {
            $categorias_despesa[] = "'" . $cat['nome'] . "'";
        } else {
            $categorias_receita[] = "'" . $cat['nome'] . "'";
        }
    }
    $lista_cat_despesa = implode(', ', $categorias_despesa);
    $lista_cat_receita = implode(', ', $categorias_receita);

} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Erro ao buscar categorias no banco de dados.']));
}

// ==========================================================
// INÍCIO DA ENGENHARIA DE PROMPT AVANÇADA
// ==========================================================
$prompt = "
    Analise o texto a seguir para extrair os detalhes de uma transação financeira e retorne APENAS um objeto JSON válido.

    **IMPORTANTE: Seja FLEXÍVEL na interpretação. Mesmo que o usuário não forneça todos os detalhes explicitamente, tente inferir informações razoáveis.**

    **Sua Tarefa Principal:**
    1.  Extraia 'descricao', 'valor' e 'data'.
    2.  Determine o 'tipo' ('receita' ou 'despesa') com base em palavras-chave como 'gastei', 'comprei', 'recebi', 'vendi', 'paguei', 'ganhei'.
    3.  **Decisão de Categoria (IMPORTANTE):**
        - **Primeiro, tente encaixar** a transação em uma das categorias existentes. A lista de despesas é: [$lista_cat_despesa]. A lista de receitas é: [$lista_cat_receita].
        - **Se não conseguir encaixar perfeitamente**, escolha a categoria mais próxima ou similar.
        - **Apenas se realmente não houver nenhuma categoria relacionada**, crie uma nova usando 'nova_categoria_nome' e 'nova_categoria_tipo'.

    **Regras de Inferência:**
    - Se não houver valor explícito, mas houver contexto (ex: 'almoço'), use valores típicos razoáveis.
    - Se não houver data explícita, assuma 'hoje': " . date('Y-m-d') . ".
    - Se não houver categoria explícita, infira pela descrição (ex: 'pizza' = Alimentação, 'uber' = Transporte).
    - Para descrições vagas como 'comprei algo', seja mais específico baseado no contexto.

    **Regras de Formato:**
    - 'valor' deve ser um número com '.' como separador decimal.
    - 'data' deve estar no formato 'YYYY-MM-DD'. 'Hoje' é " . date('Y-m-d') . ", 'ontem' é " . date('Y-m-d', strtotime('-1 day')) . ".

    **Texto do usuário:**
    \"" . $texto_usuario . "\"

    **Exemplo de Saída 1 (Categoria Existente):**
    {
      \"descricao\": \"Conta de luz de Agosto\",
      \"valor\": 150.75,
      \"data\": \"2025-08-01\",
      \"categoria_nome\": \"Contas\" 
    }

    **Exemplo de Saída 2 (Nova Categoria):**
    {
      \"descricao\": \"Rendimento do Tesouro Direto\",
      \"valor\": 55.40,
      \"data\": \"2025-08-01\",
      \"nova_categoria_nome\": \"Investimentos\",
      \"nova_categoria_tipo\": \"receita\"
    }
";
// ==========================================================
// FIM DA ENGENHARIA DE PROMPT
// ==========================================================

// --- FUNÇÃO PARA CHAMAR API GEMINI COM RETRY E FALLBACK ---
function callGeminiAPI(string $prompt, int $maxRetries = 2): array {
    // Tentar primeiro com gemini-2.0-flash-exp, se falhar com cota excedida, tentar com gemini-1.5-flash
    $models = [
        'gemini-2.0-flash-exp',
        'gemini-1.5-flash-latest' // Fallback para modelo mais estável
    ];
    
    $currentModelIndex = 0;
    
    while ($currentModelIndex < count($models)) {
        $model = $models[$currentModelIndex];
        $gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . GEMINI_API_KEY;
        $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
        
        $attempt = 0;
        $backoffSeconds = 2;
        
        while ($attempt <= $maxRetries) {
            $ch = curl_init($gemini_api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response_string = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Verificar erros de conectividade
            if ($curl_error) {
                if ($attempt < $maxRetries) {
                    sleep($backoffSeconds);
                    $backoffSeconds *= 2;
                    $attempt++;
                    continue;
                }
                // Se último modelo e última tentativa, retorna erro
                if ($currentModelIndex === count($models) - 1) {
                    return [
                        'success' => false,
                        'http_code' => 0,
                        'message' => 'Erro de conexão com o serviço de IA. Tente novamente em alguns instantes.',
                        'response' => '',
                        'error' => $curl_error,
                        'model_used' => $model
                    ];
                }
                // Tenta próximo modelo
                break;
            }
            
            // Se sucesso, retorna
            if ($http_code === 200) {
                return [
                    'success' => true,
                    'http_code' => 200,
                    'message' => '',
                    'response' => $response_string,
                    'error' => null,
                    'model_used' => $model
                ];
            }
            
            // Se erro 429, analisar
            if ($http_code === 429) {
                $response_data = json_decode($response_string, true);
                $error_message = $response_data['error']['message'] ?? '';
                $error_details = $response_data['error'] ?? [];
                
                // Verificar se é cota excedida com limit: 0 (baseado no erro real recebido)
                $isQuotaExceeded = false;
                $hasLimitZero = stripos($error_message, 'limit: 0') !== false;
                
                // Verificar violations no formato exato do erro (QuotaFailure com violations)
                if (isset($error_details['details']) && is_array($error_details['details'])) {
                    foreach ($error_details['details'] as $detail) {
                        // Verificar se é QuotaFailure (tipo do erro que o usuário recebeu)
                        if (isset($detail['@type']) && stripos($detail['@type'], 'QuotaFailure') !== false) {
                            if (isset($detail['violations']) && is_array($detail['violations'])) {
                                foreach ($detail['violations'] as $violation) {
                                    // Verificar se é free_tier (indicador de plano gratuito)
                                    if (isset($violation['quotaMetric']) && 
                                        stripos($violation['quotaMetric'], 'free_tier') !== false) {
                                        $isQuotaExceeded = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Se a mensagem menciona "limit: 0", é definitivamente cota excedida do plano gratuito
                if ($hasLimitZero) {
                    $isQuotaExceeded = true;
                }
                
                // Verificação adicional na mensagem de erro
                if (stripos($error_message, 'free_tier') !== false && 
                    stripos($error_message, 'quota exceeded') !== false) {
                    $isQuotaExceeded = true;
                }
                
                // Extrair tempo de retry
                $retryAfterSeconds = $backoffSeconds;
                if (preg_match('/retry in ([\d.]+)s/i', $error_message, $matches)) {
                    $retryAfterSeconds = (int)ceil((float)$matches[1]);
                } elseif (preg_match('/Please retry in ([\d.]+)s/i', $error_message, $matches)) {
                    $retryAfterSeconds = (int)ceil((float)$matches[1]);
                }
                
                // Se for cota excedida com limit: 0, não tenta modelo alternativo (todos estão sem cota)
                // Se for cota excedida sem limit: 0 explícito, pode tentar modelo alternativo
                if ($isQuotaExceeded && !$hasLimitZero && $currentModelIndex < count($models) - 1) {
                    $currentModelIndex++;
                    break; // Sai do loop de tentativas e tenta próximo modelo
                }
                
                // Se rate limit temporário e ainda há tentativas, aguarda e tenta novamente
                if ($attempt < $maxRetries && !$isQuotaExceeded) {
                    sleep(min($retryAfterSeconds, 30));
                    $backoffSeconds *= 2;
                    $attempt++;
                    continue;
                }
                
                // Se chegou aqui, é cota excedida definitiva ou sem mais tentativas
                $error_details_msg = '';
                if ($isQuotaExceeded) {
                    if ($hasLimitZero) {
                        // Cota excedida com limit: 0 - plano gratuito sem cota disponível
                        $error_details_msg = "A cota gratuita da API do Gemini foi excedida (limit: 0). O plano gratuito atingiu seu limite diário/mensal. Aguarde algumas horas para a cota ser resetada ou considere atualizar seu plano na Google Cloud. Você ainda pode adicionar transações manualmente usando o formulário abaixo.";
                        $retryAfterSeconds = max($retryAfterSeconds, 3600); // Mínimo 1 hora
                    } else {
                        // Cota excedida mas sem limit: 0 explícito
                        $error_details_msg = "A cota da API do Gemini foi excedida. {$error_message} Por favor, aguarde algumas horas ou considere atualizar seu plano. Você ainda pode adicionar transações manualmente.";
                        $retryAfterSeconds = max($retryAfterSeconds, 1800); // Mínimo 30 minutos
                    }
                } else {
                    // Rate limit temporário (não é cota excedida)
                    $error_details_msg = "Limite de requisições temporário na API do Gemini. {$error_message} Aguarde {$retryAfterSeconds} segundos e tente novamente.";
                }
                
                return [
                    'success' => false,
                    'http_code' => 429,
                    'message' => $error_details_msg,
                    'response' => $response_string,
                    'error' => null,
                    'model_used' => $model,
                    'retry_after_seconds' => $retryAfterSeconds,
                    'quota_exceeded' => $isQuotaExceeded
                ];
            }
            
            // Outros erros HTTP
            $error_details = '';
            switch ($http_code) {
                case 400:
                    $error_details = 'Requisição inválida. Verifique o formato dos dados.';
                    break;
                case 401:
                    $error_details = 'Chave API inválida ou expirada.';
                    break;
                case 403:
                    $error_details = 'Acesso negado. Verifique as permissões da API.';
                    break;
                case 500:
                case 502:
                case 503:
                    $error_details = "Erro temporário no servidor da Google ($http_code). Tente novamente em alguns minutos.";
                    break;
                default:
                    $error_details = "Erro HTTP $http_code. Tente novamente mais tarde.";
            }
            
            return [
                'success' => false,
                'http_code' => $http_code,
                'message' => $error_details,
                'response' => $response_string,
                'error' => null,
                'model_used' => $model
            ];
        }
        
        // Se chegou aqui, tentou todas as tentativas deste modelo
        // Se não é o último modelo, tenta próximo
        if ($currentModelIndex < count($models) - 1) {
            $currentModelIndex++;
            continue;
        }
        
        // Se é o último modelo, retorna erro
        break;
    }
    
    // Se chegou aqui, tentou todos os modelos e falhou
    return [
        'success' => false,
        'http_code' => 429,
        'message' => 'Não foi possível processar após várias tentativas com todos os modelos disponíveis. A cota da API pode estar excedida. Aguarde algumas horas ou use o formulário manual.',
        'response' => '',
        'error' => 'Max retries exceeded for all models',
        'quota_exceeded' => true
    ];
}

// --- CHAMADA PARA A API DO GEMINI COM RETRY ---
$apiResult = callGeminiAPI($prompt, 2);

if (!$apiResult['success']) {
    $http_code = $apiResult['http_code'];
    
    // Se for erro 429, retorna código 429 para o cliente
    if ($http_code === 429) {
        http_response_code(429);
        $rateLimitInfo = [];
        try {
            if (isset($rateLimiter) && $rateLimiter !== null) {
                $rateLimitInfo = $rateLimiter->getUsageStats($userId, 'gemini');
            }
        } catch (Exception $e) {
            // Ignora erro ao obter stats
        }
        
        $errorMessage = $apiResult['message'];
        $isQuotaExceeded = $apiResult['quota_exceeded'] ?? false;
        $retryAfter = $apiResult['retry_after_seconds'] ?? 60;
        
        exit(json_encode([
            'success' => false,
            'message' => $errorMessage,
            'retry_after' => $retryAfter,
            'rate_limit_info' => $rateLimitInfo,
            'quota_exceeded' => $isQuotaExceeded,
            'model_used' => $apiResult['model_used'] ?? 'unknown'
        ]));
    }
    
    // Outros erros
    http_response_code($http_code >= 400 && $http_code < 500 ? $http_code : 500);
    exit(json_encode([
        'success' => false,
        'message' => $apiResult['message'],
        'debug' => 'HTTP Code: ' . $http_code,
        'response' => substr($apiResult['response'], 0, 500)
    ]));
}

$response_string = $apiResult['response'];

$response_data = json_decode($response_string, true);
$json_text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Verificar se a resposta da API está vazia
if (empty($json_text)) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'A IA não conseguiu processar sua solicitação. Tente reformular com mais detalhes.']));
}

$json_text_cleaned = trim(str_replace(['```json', '```'], '', $json_text));
$resultado_ia = json_decode($json_text_cleaned, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($resultado_ia)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Não consegui processar sua solicitação. Tente incluir mais detalhes como: descrição, valor e categoria. Exemplo: "Comprei pizza por R$ 25 em Alimentação".']));
}

// Validação mais específica dos campos obrigatórios
if (empty($resultado_ia['descricao']) || empty($resultado_ia['valor']) || empty($resultado_ia['data'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Informações incompletas. Por favor, inclua pelo menos: o que foi (descrição), quanto custou (valor) e quando foi (data ou "hoje/ontem"). Exemplo: "Almoço R$ 30 hoje".']));
}

// --- LÓGICA PARA SALVAR NO BANCO DE DADOS ---
try {
    $id_categoria = null;
    $tipo_transacao = '';
    // Definir conta alvo
    $id_conta = null;
    if ($id_conta_req) {
        $stmt_chk = $pdo->prepare("SELECT id FROM contas WHERE id = ? AND id_usuario = ?");
        $stmt_chk->execute([$id_conta_req, $userId]);
        if ($stmt_chk->fetchColumn()) {
            $id_conta = $id_conta_req;
        }
    }
    if (!$id_conta) {
        $stmt_default = $pdo->prepare("SELECT id FROM contas WHERE id_usuario = ? ORDER BY id ASC LIMIT 1");
        $stmt_default->execute([$userId]);
        $id_conta = $stmt_default->fetchColumn();
        if (!$id_conta) {
            $stmt_ins = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, saldo_inicial) VALUES (?, 'Geral', 'dinheiro', 0)");
            $stmt_ins->execute([$userId]);
            $id_conta = $pdo->lastInsertId();
        }
    }

    // Se a IA sugeriu uma NOVA categoria
    if (isset($resultado_ia['nova_categoria_nome'])) {
        $nova_cat_nome = $resultado_ia['nova_categoria_nome'];
        $nova_cat_tipo = $resultado_ia['nova_categoria_tipo'];

        // Insere a nova categoria no banco
        $stmt_nova_cat = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
        $stmt_nova_cat->execute([$userId, $nova_cat_nome, $nova_cat_tipo]);
        $id_categoria = $pdo->lastInsertId();
        $tipo_transacao = $nova_cat_tipo;
    
    // Se a IA usou uma categoria EXISTENTE
    } elseif (isset($resultado_ia['categoria_nome'])) {
        $stmt_cat_id = $pdo->prepare("SELECT id, tipo FROM categorias WHERE nome = ? AND id_usuario = ?");
        $stmt_cat_id->execute([$resultado_ia['categoria_nome'], $userId]);
        $categoria_info = $stmt_cat_id->fetch(PDO::FETCH_ASSOC);

        if (!$categoria_info) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => "A categoria '" . htmlspecialchars($resultado_ia['categoria_nome']) . "' sugerida pela IA não foi encontrada."]));
        }
        $id_categoria = $categoria_info['id'];
        $tipo_transacao = $categoria_info['tipo'];
    } else {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'A IA não conseguiu definir uma categoria.']));
    }

    // Finalmente, insere a transação
    $sql = "INSERT INTO transacoes (id_usuario, id_categoria, id_conta, descricao, valor, tipo, data_transacao) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([ $userId, $id_categoria, $id_conta, $resultado_ia['descricao'], $resultado_ia['valor'], $tipo_transacao, $resultado_ia['data'] ]);

    echo json_encode(['success' => true, 'message' => 'Lançamento adicionado pela IA com sucesso!']);

} catch (PDOException $e) {
    http_response_code(500);
    // Adiciona a mensagem de erro do banco de dados para depuração
    exit(json_encode(['success' => false, 'message' => 'Erro ao salvar no banco de dados: ' . $e->getMessage()]));
}
?>
