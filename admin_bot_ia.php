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
    
    // 8.1. Funções para executar ações no painel
    function adicionarTarefa(PDO $pdo, int $userId, string $descricao, string $prioridade = 'Média', ?string $dataLimite = null): array {
        try {
            if (empty(trim($descricao))) {
                return ['success' => false, 'message' => 'Descrição da tarefa é obrigatória'];
            }
            
            $prioridade = in_array($prioridade, ['Alta', 'Média', 'Baixa']) ? $prioridade : 'Média';
            
            $dataLimiteSQL = null;
            if ($dataLimite) {
                // Converter formato brasileiro (dd/mm/yyyy) para SQL (yyyy-mm-dd)
                $parts = explode('/', $dataLimite);
                if (count($parts) === 3) {
                    $dataLimiteSQL = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO tarefas (id_usuario, descricao, prioridade, data_limite, status, data_criacao) VALUES (?, ?, ?, ?, 'pendente', NOW())");
            $stmt->execute([$userId, trim($descricao), $prioridade, $dataLimiteSQL]);
            
            return ['success' => true, 'message' => "Tarefa '{$descricao}' adicionada com sucesso!"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao adicionar tarefa: ' . $e->getMessage()];
        }
    }
    
    function removerTarefa(PDO $pdo, int $userId, string $descricaoOuId): array {
        try {
            if (empty(trim($descricaoOuId))) {
                return ['success' => false, 'message' => 'É necessário informar o ID ou descrição da tarefa'];
            }
            
            if (is_numeric($descricaoOuId)) {
                $stmt = $pdo->prepare("SELECT id, descricao FROM tarefas WHERE id = ? AND id_usuario = ?");
                $stmt->execute([(int)$descricaoOuId, $userId]);
            } else {
                $stmt = $pdo->prepare("SELECT id, descricao FROM tarefas WHERE id_usuario = ? AND descricao LIKE ? AND status = 'pendente' LIMIT 1");
                $stmt->execute([$userId, '%' . $descricaoOuId . '%']);
            }
            
            $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tarefa) {
                return ['success' => false, 'message' => 'Tarefa não encontrada'];
            }
            
            // Deletar subtarefas primeiro
            $stmt_sub = $pdo->prepare("DELETE FROM subtarefas WHERE id_tarefa_principal = ?");
            $stmt_sub->execute([$tarefa['id']]);
            
            // Deletar a tarefa
            $stmt_del = $pdo->prepare("DELETE FROM tarefas WHERE id = ? AND id_usuario = ?");
            $stmt_del->execute([$tarefa['id'], $userId]);
            
            return ['success' => true, 'message' => "Tarefa '{$tarefa['descricao']}' removida com sucesso!"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao remover tarefa: ' . $e->getMessage()];
        }
    }
    
    function adicionarTransacao(PDO $pdo, int $userId, float $valor, string $tipo, string $descricao, ?string $data = null): array {
        try {
            if ($valor <= 0) {
                return ['success' => false, 'message' => 'Valor deve ser maior que zero'];
            }
            
            if (!in_array($tipo, ['receita', 'despesa'])) {
                return ['success' => false, 'message' => 'Tipo deve ser "receita" ou "despesa"'];
            }
            
            // Buscar ou criar categoria padrão
            $stmt_cat = $pdo->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND tipo = ? ORDER BY id ASC LIMIT 1");
            $stmt_cat->execute([$userId, $tipo]);
            $categoria = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            
            if (!$categoria) {
                // Criar categoria padrão
                $stmt_ins_cat = $pdo->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
                $stmt_ins_cat->execute([$userId, 'Outros', $tipo]);
                $id_categoria = $pdo->lastInsertId();
            } else {
                $id_categoria = $categoria['id'];
            }
            
            // Buscar ou criar conta padrão
            $stmt_conta = $pdo->prepare("SELECT id FROM contas WHERE id_usuario = ? ORDER BY id ASC LIMIT 1");
            $stmt_conta->execute([$userId]);
            $conta = $stmt_conta->fetch(PDO::FETCH_ASSOC);
            
            if (!$conta) {
                $stmt_ins_conta = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, saldo_inicial) VALUES (?, 'Geral', 'dinheiro', 0)");
                $stmt_ins_conta->execute([$userId]);
                $id_conta = $pdo->lastInsertId();
            } else {
                $id_conta = $conta['id'];
            }
            
            $dataTransacao = $data ? date('Y-m-d', strtotime(str_replace('/', '-', $data))) : date('Y-m-d');
            
            $stmt = $pdo->prepare("INSERT INTO transacoes (id_usuario, id_categoria, id_conta, descricao, valor, tipo, data_transacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $id_categoria, $id_conta, trim($descricao), $valor, $tipo, $dataTransacao]);
            
            return ['success' => true, 'message' => "Transação de {$tipo} no valor de R$ " . number_format($valor, 2, ',', '.') . " registrada com sucesso!"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erro ao adicionar transação: ' . $e->getMessage()];
        }
    }
    
    // 9. Sistema de Fallback em Cascata - Tentar múltiplos modelos sequencialmente
    $models = [
        'gemini-2.5-flash',      // Primary - modelo mais recente
        'gemini-1.5-flash',      // Standard fallback
        'gemini-1.5-flash-001',  // Legacy stable
        'gemini-1.5-pro'         // High capacity fallback
    ];
    
    $prompt = "Você é um assistente financeiro especializado em ajudar usuários a gerenciar suas finanças e tarefas através do WhatsApp.\n\n";
    $prompt .= $systemContext . "\n";
    $prompt .= "PERGUNTA DO USUÁRIO: {$pergunta}\n\n";
    $prompt .= "INSTRUÇÕES:\n";
    $prompt .= "- Se o usuário pedir para ADICIONAR uma tarefa, use a função adicionarTarefa.\n";
    $prompt .= "- Se o usuário pedir para REMOVER/APAGAR uma tarefa, use a função removerTarefa.\n";
    $prompt .= "- Se o usuário pedir para REGISTRAR uma receita ou despesa, use a função adicionarTransacao.\n";
    $prompt .= "- Sempre execute as ações solicitadas pelo usuário usando as funções disponíveis.\n";
    $prompt .= "- Após executar uma ação, confirme ao usuário de forma clara e objetiva.\n";
    $prompt .= "- Formate números monetários em R$ e datas em formato brasileiro (dd/mm/aaaa).";
    
    // Definir tools (funções) disponíveis para a IA
    $tools = [
        [
            'functionDeclarations' => [
                [
                    'name' => 'adicionarTarefa',
                    'description' => 'Adiciona uma nova tarefa ao painel. Use quando o usuário pedir para criar, adicionar ou registrar uma tarefa.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'descricao' => [
                                'type' => 'string',
                                'description' => 'Descrição da tarefa a ser criada'
                            ],
                            'prioridade' => [
                                'type' => 'string',
                                'description' => 'Prioridade da tarefa: "Alta", "Média" ou "Baixa". Padrão: "Média"',
                                'enum' => ['Alta', 'Média', 'Baixa']
                            ],
                            'dataLimite' => [
                                'type' => 'string',
                                'description' => 'Data limite da tarefa no formato dd/mm/yyyy (opcional)'
                            ]
                        ],
                        'required' => ['descricao']
                    ]
                ],
                [
                    'name' => 'removerTarefa',
                    'description' => 'Remove uma tarefa existente do painel. Use quando o usuário pedir para remover, apagar, deletar ou excluir uma tarefa.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'descricaoOuId' => [
                                'type' => 'string',
                                'description' => 'ID numérico da tarefa ou parte da descrição da tarefa a ser removida'
                            ]
                        ],
                        'required' => ['descricaoOuId']
                    ]
                ],
                [
                    'name' => 'adicionarTransacao',
                    'description' => 'Registra uma nova transação financeira (receita ou despesa) no painel. Use quando o usuário pedir para registrar, adicionar ou criar uma receita ou despesa.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'valor' => [
                                'type' => 'number',
                                'description' => 'Valor da transação (deve ser maior que zero)'
                            ],
                            'tipo' => [
                                'type' => 'string',
                                'description' => 'Tipo da transação: "receita" ou "despesa"',
                                'enum' => ['receita', 'despesa']
                            ],
                            'descricao' => [
                                'type' => 'string',
                                'description' => 'Descrição da transação'
                            ],
                            'data' => [
                                'type' => 'string',
                                'description' => 'Data da transação no formato dd/mm/yyyy (opcional, padrão: hoje)'
                            ]
                        ],
                        'required' => ['valor', 'tipo', 'descricao']
                    ]
                ]
            ]
        ]
    ];
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'tools' => $tools
    ];
    
    $lastError = null;
    $lastErrorCode = null;
    $lastErrorMessage = null;
    $success = false;
    $respostaFinal = null;
    
    // Tentar cada modelo sequencialmente
    foreach ($models as $model) {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;
        
        error_log("[BOT_IA] Tentando modelo: {$model}");
        
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
        
        // Erro de conexão cURL - tentar próximo modelo
        if ($curlError) {
            error_log("[BOT_IA] Erro cURL com modelo {$model}: $curlError");
            $lastError = "Erro de conexão: $curlError";
            continue;
        }
        
        // Sucesso (HTTP 200) - processar resposta e sair do loop
        if ($httpCode === 200) {
            $apiResponse = json_decode($response, true);
            if (!$apiResponse) {
                error_log("[BOT_IA] Resposta JSON inválida do modelo {$model}");
                $lastError = "Resposta inválida da API";
                continue;
            }
            
            $candidate = $apiResponse['candidates'][0] ?? null;
            if (!$candidate) {
                error_log("[BOT_IA] Candidato não encontrado na resposta do modelo {$model}");
                $lastError = "Resposta inválida da API";
                continue;
            }
            
            $parts = $candidate['content']['parts'] ?? [];
            $functionCalls = [];
            $textResponse = null;
            
            // Processar partes da resposta
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionCalls[] = $part['functionCall'];
                } elseif (isset($part['text'])) {
                    $textResponse = $part['text'];
                }
            }
            
            // Se houver function calls, executar e enviar resultado de volta para a IA
            if (!empty($functionCalls)) {
                error_log("[BOT_IA] Funções chamadas: " . count($functionCalls));
                
                $functionResults = [];
                foreach ($functionCalls as $functionCall) {
                    $functionName = $functionCall['name'] ?? '';
                    $args = $functionCall['args'] ?? [];
                    
                    error_log("[BOT_IA] Executando função: {$functionName} com args: " . json_encode($args));
                    
                    $result = null;
                    try {
                        switch ($functionName) {
                            case 'adicionarTarefa':
                                $result = adicionarTarefa(
                                    $pdo,
                                    $userId,
                                    $args['descricao'] ?? '',
                                    $args['prioridade'] ?? 'Média',
                                    $args['dataLimite'] ?? null
                                );
                                break;
                            case 'removerTarefa':
                                $result = removerTarefa($pdo, $userId, $args['descricaoOuId'] ?? '');
                                break;
                            case 'adicionarTransacao':
                                $result = adicionarTransacao(
                                    $pdo,
                                    $userId,
                                    (float)($args['valor'] ?? 0),
                                    $args['tipo'] ?? '',
                                    $args['descricao'] ?? '',
                                    $args['data'] ?? null
                                );
                                break;
                            default:
                                $result = ['success' => false, 'message' => "Função '{$functionName}' não encontrada"];
                        }
                    } catch (Exception $e) {
                        $result = ['success' => false, 'message' => 'Erro ao executar função: ' . $e->getMessage()];
                    }
                    
                    $functionResults[] = [
                        'functionResponse' => [
                            'name' => $functionName,
                            'response' => $result
                        ]
                    ];
                }
                
                // Enviar resultados das funções de volta para a IA gerar resposta final
                $data['contents'][] = [
                    'parts' => $functionResults
                ];
                
                // Segunda chamada para obter resposta final da IA
                $ch2 = curl_init($apiUrl);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                
                $response2 = curl_exec($ch2);
                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                if ($httpCode2 === 200) {
                    $apiResponse2 = json_decode($response2, true);
                    if ($apiResponse2 && isset($apiResponse2['candidates'][0]['content']['parts'][0]['text'])) {
                        $respostaFinal = $apiResponse2['candidates'][0]['content']['parts'][0]['text'];
                        $success = true;
                        error_log("[BOT_IA] Sucesso com modelo: {$model} (após executar funções)");
                        break;
                    }
                }
            } elseif ($textResponse) {
                // Resposta direta sem function calls
                $respostaFinal = $textResponse;
                $success = true;
                error_log("[BOT_IA] Sucesso com modelo: {$model}");
                break;
            } else {
                error_log("[BOT_IA] Resposta inválida do modelo {$model}");
                $lastError = "Resposta inválida da API";
                continue;
            }
        }
        
        // Rate Limit (429) - parar imediatamente (é account-wide)
        if ($httpCode === 429) {
            error_log("[BOT_IA] Rate Limit excedido (HTTP 429) - parando tentativas");
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'resposta' => '⏳ Limite de requisições (5 RPM) atingido. Aguarde alguns minutos antes de tentar novamente.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Outros erros (404, 400, 500, etc.) - tentar próximo modelo
        $errorMessage = 'Erro desconhecido';
        $errorCode = '';
        
        if ($response) {
            $errorData = json_decode($response, true);
            if (isset($errorData['error'])) {
                if (isset($errorData['error']['code'])) {
                    $errorCode = $errorData['error']['code'];
                }
                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                } elseif (isset($errorData['error']['status'])) {
                    $errorMessage = $errorData['error']['status'];
                }
            } else {
                $errorMessage = substr($response, 0, 500);
            }
        }
        
        error_log("[BOT_IA] Erro HTTP $httpCode com modelo {$model}: {$errorMessage}");
        
        // Armazenar último erro para retornar se todos falharem
        $lastError = "Erro Google";
        if ($errorCode) {
            $lastError .= " [{$errorCode}]";
        } else {
            $lastError .= " [HTTP {$httpCode}]";
        }
        $lastError .= ": {$errorMessage}";
        $lastErrorCode = $httpCode;
        $lastErrorMessage = $errorMessage;
        
        // Continuar para próximo modelo
        continue;
    }
    
    // Se nenhum modelo funcionou, retornar erro
    if (!$success) {
        error_log("[BOT_IA] Todos os modelos falharam. Último erro: {$lastError}");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'resposta' => $lastError ?: 'Todos os modelos de IA falharam. Tente novamente mais tarde.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
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
