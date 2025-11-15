<?php
// /processar_analise_ia.php (Versão Final e Robusta para Tarefas)

session_start();
header('Content-Type: application/json');

// --- VALIDAÇÃO E INICIALIZAÇÃO ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}
require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';

// Verificar se o arquivo de config existe (pode não existir em desenvolvimento)
if (file_exists('/home/u853242961/config/config.php')) {
    require_once '/home/u853242961/config/config.php';
} elseif (defined('GEMINI_API_KEY')) {
    // Já definido no db_connect.php
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuração da API não encontrada.']);
    exit();
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$pergunta_usuario = $input['pergunta'] ?? '';
if (empty($pergunta_usuario)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nenhuma pergunta fornecida.']);
    exit();
}

// Verificar rate limiting (com tratamento de erro)
try {
    $rateLimiter = new RateLimiter($pdo);
    $rateLimitCheck = $rateLimiter->checkRateLimit($userId, 'gemini');

    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => $rateLimitCheck['message'],
            'retry_after' => $rateLimitCheck['retry_after'],
            'limit_type' => $rateLimitCheck['limit_type'],
            'rate_limit_info' => $rateLimiter->getUsageStats($userId, 'gemini')
        ]);
        exit();
    }
} catch (Exception $e) {
    // Se houver erro no rate limiter, continua sem rate limiting (modo degradado)
    error_log("Rate Limiter Error: " . $e->getMessage());
    // Continua com a requisição normalmente
}

// =================================================================================
// FUNÇÕES DAS FERRAMENTAS
// =================================================================================

function getResumoFinanceiro(PDO $pdo, int $userId): array { /* ...código sem alteração... */
    $sql = "SELECT SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as total_receitas, SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as total_despesas FROM transacoes WHERE id_usuario = ? AND MONTH(data_transacao) = MONTH(CURDATE()) AND YEAR(data_transacao) = YEAR(CURDATE())";
    $stmt = $pdo->prepare($sql); $stmt->execute([$userId]); $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['total_receitas' => number_format($resumo['total_receitas'] ?? 0, 2, ',', '.'), 'total_despesas' => number_format($resumo['total_despesas'] ?? 0, 2, ',', '.'),];
}
function getPrincipaisCategoriasGasto(PDO $pdo, int $userId): array { /* ...código sem alteração... */
    $sql = "SELECT c.nome, SUM(t.valor) as total FROM transacoes t JOIN categorias c ON t.id_categoria = c.id WHERE t.id_usuario = ? AND t.tipo = 'despesa' AND t.data_transacao >= CURDATE() - INTERVAL 30 DAY GROUP BY c.nome ORDER BY total DESC LIMIT 3";
    $stmt = $pdo->prepare($sql); $stmt->execute([$userId]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function cadastrarTransacao(PDO $pdo, int $userId, string $tipo, float $valor, string $descricao, string $nome_categoria): array { /* ...código sem alteração... */
    if ($valor <= 0) { return ['success' => false, 'message' => 'O valor da transação deve ser positivo.']; }
    if ($tipo !== 'receita' && $tipo !== 'despesa') { return ['success' => false, 'message' => 'Tipo inválido.']; }
    $nome_categoria_limpo = ucfirst(trim($nome_categoria));
    $sql_find_category = "SELECT id FROM categorias WHERE LOWER(nome) = LOWER(?) AND (id_usuario = ? OR id_usuario IS NULL)";
    $stmt_cat = $pdo->prepare($sql_find_category); $stmt_cat->execute([$nome_categoria_limpo, $userId]); $categoria = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    $categoria_criada_msg = '';
    if (!$categoria) {
        $sql_create_category = "INSERT INTO categorias (id_usuario, nome) VALUES (?, ?)";
        $stmt_create = $pdo->prepare($sql_create_category); $stmt_create->execute([$userId, $nome_categoria_limpo]);
        $id_categoria = $pdo->lastInsertId();
        $categoria_criada_msg = " Criei a nova categoria '{$nome_categoria_limpo}' para você.";
    } else { $id_categoria = $categoria['id']; }
    $sql = "INSERT INTO transacoes (id_usuario, tipo, valor, descricao, id_categoria, data_transacao) VALUES (?, ?, ?, ?, ?, NOW())";
    try {
        $stmt = $pdo->prepare($sql); $stmt->execute([$userId, $tipo, $valor, $descricao, $id_categoria]);
        $success_message = "Transação de R$ ".number_format($valor, 2, ',', '.')." na categoria '{$nome_categoria_limpo}' cadastrada com sucesso." . $categoria_criada_msg;
        return ['success' => $stmt->rowCount() > 0, 'message' => $stmt->rowCount() > 0 ? $success_message : 'Não foi possível cadastrar a transação.'];
    } catch (PDOException $e) { error_log("Erro ao cadastrar transação: " . $e->getMessage()); return ['success' => false, 'message' => 'Erro no banco de dados.']; }
}

/**
 * Busca as tarefas pendentes do usuário.
 */
function getTarefasDoUsuario(PDO $pdo, int $userId): array {
    $sql = "SELECT id, descricao, prioridade, data_limite, 
            CASE 
                WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 'Vencida'
                WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'Urgente'
                WHEN prioridade = 'Alta' THEN 'Alta Prioridade'
                ELSE 'Normal'
            END as status_urgencia
            FROM tarefas 
            WHERE id_usuario = ? AND status = 'pendente' 
            ORDER BY 
                CASE WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 1 ELSE 2 END,
                CASE WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 2 END,
                FIELD(prioridade, 'Alta', 'Média', 'Baixa'),
                data_limite ASC,
                ordem ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se não houver tarefas, retorne uma mensagem clara.
    if (empty($tarefas)) {
        return ['resultado' => 'O usuário não possui tarefas pendentes.'];
    }
    // Retorna a lista de tarefas, que a IA vai formatar.
    return ['tarefas_pendentes' => $tarefas];
}

/**
 * Busca as tarefas mais urgentes do usuário (prioridade Alta ou com data limite próxima/vencida).
 */
function getTarefasUrgentes(PDO $pdo, int $userId): array {
    $sql = "SELECT id, descricao, prioridade, data_limite,
            CASE 
                WHEN data_limite IS NOT NULL AND data_limite <= CURDATE() THEN 'Vencida'
                WHEN data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'Urgente'
                ELSE 'Alta Prioridade'
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
                data_limite ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($tarefas)) {
        return ['resultado' => 'O usuário não possui tarefas urgentes no momento.'];
    }
    return ['tarefas_urgentes' => $tarefas];
}

function adicionarTarefa(PDO $pdo, int $userId, string $descricao): array { /* ...código sem alteração... */
    if (empty($descricao)) { return ['success' => false, 'message' => 'A descrição da tarefa não pode estar vazia.']; }
    $stmt_ordem = $pdo->prepare("SELECT MAX(ordem) as max_ordem FROM tarefas WHERE id_usuario = ?");
    $stmt_ordem->execute([$userId]); $max_ordem = $stmt_ordem->fetchColumn();
    $nova_ordem = ($max_ordem === null) ? 0 : $max_ordem + 1;
    $prioridade_padrao = 'Média';
    $sql = "INSERT INTO tarefas (id_usuario, descricao, status, data_criacao, prioridade, ordem) VALUES (?, ?, 'pendente', NOW(), ?, ?)";
    try {
        $stmt = $pdo->prepare($sql); $stmt->execute([$userId, $descricao, $prioridade_padrao, $nova_ordem]);
        if ($stmt->rowCount() > 0) { return ['success' => true, 'message' => "Tarefa '{$descricao}' adicionada com sucesso."]; }
        return ['success' => false, 'message' => 'Não foi possível adicionar a tarefa.'];
    } catch (PDOException $e) { error_log("Erro ao adicionar tarefa: " . $e->getMessage()); return ['success' => false, 'message' => 'Erro no banco de dados.']; }
}

// =================================================================================
// DEFINIÇÃO DAS FERRAMENTAS
// =================================================================================
$tools = [['functionDeclarations' => [
    ['name' => 'getResumoFinanceiro', 'description' => 'Obtém o resumo de receitas e despesas totais do mês atual do usuário.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object)[]]], 
    ['name' => 'getPrincipaisCategoriasGasto', 'description' => 'Obtém as 3 principais categorias de despesas do usuário nos últimos 30 dias.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object)[]]], 
    ['name' => 'cadastrarTransacao', 'description' => 'Cadastra uma nova transação financeira (receita ou despesa) para o usuário.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['tipo' => ['type' => 'STRING'], 'valor' => ['type' => 'NUMBER'], 'descricao' => ['type' => 'STRING'], 'nome_categoria' => ['type' => 'STRING']], 'required' => ['tipo', 'valor', 'descricao', 'nome_categoria']]], 
    ['name' => 'getTarefasDoUsuario', 'description' => 'Obtém a lista completa de tarefas pendentes do usuário, ordenadas por urgência (vencidas primeiro, depois por prioridade e data limite).', 'parameters' => ['type' => 'OBJECT', 'properties' => (object)[]]], 
    ['name' => 'getTarefasUrgentes', 'description' => 'Obtém apenas as tarefas mais urgentes do usuário: tarefas com prioridade Alta OU com data limite nos próximos 7 dias. Use SEMPRE esta função quando o usuário perguntar sobre "tarefas urgentes", "tarefas mais importantes", "tarefas prioritárias", "o que preciso fazer com urgência" ou qualquer variação similar. Esta é a função correta para perguntas sobre urgência.', 'parameters' => ['type' => 'OBJECT', 'properties' => (object)[]]], 
    ['name' => 'adicionarTarefa', 'description' => 'Adiciona um novo item à lista de tarefas do usuário.', 'parameters' => ['type' => 'OBJECT', 'properties' => ['descricao' => ['type' => 'STRING']], 'required' => ['descricao']]]
]]];

// =================================================================================
// LÓGICA DE CHAMADA DA API
// =================================================================================

$prompt_inicial = "Você é 'Orion', um assistente de finanças e produtividade. Sua tarefa é SEMPRE usar ferramentas para responder às perguntas.

REGRAS OBRIGATÓRIAS:
1. SEMPRE use uma ferramenta antes de responder. NUNCA responda de memória.
2. Para perguntas sobre tarefas urgentes/importantes/prioritárias, SEMPRE use 'getTarefasUrgentes'.
3. Para perguntas sobre tarefas em geral, use 'getTarefasDoUsuario'.
4. Para transações: 'gastei'/'comprei' = despesa, 'recebi'/'ganhei' = receita.
5. Ao receber resultados de tarefas, SEMPRE apresente em formato de lista markdown com '-'.
6. Inclua sempre: descrição, prioridade e data limite (se houver).
7. Seja amigável e encorajador no tom.

EXEMPLOS:
- 'Quais são minhas tarefas mais urgentes?' → use getTarefasUrgentes
- 'Quais são minhas tarefas?' → use getTarefasDoUsuario
- 'Onde posso economizar?' → use getPrincipaisCategoriasGasto

Lembre-se: SEMPRE use uma ferramenta primeiro, depois formule a resposta baseada no resultado.";

$gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . GEMINI_API_KEY;
$conversationHistory = [['role' => 'user', 'parts' => [['text' => $prompt_inicial]]], ['role' => 'model', 'parts' => [['text' => 'Entendido! Estou pronto para ajudar.']]], ['role' => 'user', 'parts' => [['text' => $pergunta_usuario]]]];
$data_primeira_chamada = ['contents' => $conversationHistory, 'tools' => $tools, 'tool_config' => ['function_calling_config' => ['mode' => 'ANY']]];

$ch = curl_init($gemini_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_primeira_chamada));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response_string = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Verificar erro 429
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
    
    // Verificar se é erro de cota excedida
    $response_data = json_decode($response_string, true);
    $error_message = $response_data['error']['message'] ?? 'Limite de requisições excedido.';
    $isQuotaExceeded = stripos($error_message, 'quota') !== false || 
                      stripos($error_message, 'limit: 0') !== false ||
                      stripos($error_message, 'free_tier') !== false;
    
    if ($isQuotaExceeded) {
        $error_message = 'A cota gratuita da API do Gemini foi excedida. Por favor, aguarde algumas horas ou considere atualizar seu plano na Google Cloud.';
    } else {
        $error_message = 'Limite de requisições excedido na API do Gemini. Aguarde alguns minutos antes de tentar novamente.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'retry_after' => 60,
        'rate_limit_info' => $rateLimitInfo,
        'quota_exceeded' => $isQuotaExceeded
    ]);
    exit();
}

$api_response = json_decode($response_string, true);

$resposta_final_ia = '';
$functionCall = $api_response['candidates'][0]['content']['parts'][0]['functionCall'] ?? null;
if ($functionCall) {
    $functionName = $functionCall['name'];
    $functionArgs = $functionCall['args'];
    $functionResult = null;
    switch ($functionName) {
        case 'getResumoFinanceiro': $functionResult = getResumoFinanceiro($pdo, $userId); break;
        case 'getPrincipaisCategoriasGasto': $functionResult = getPrincipaisCategoriasGasto($pdo, $userId); break;
        case 'cadastrarTransacao': $functionResult = cadastrarTransacao($pdo, $userId, $functionArgs['tipo'], $functionArgs['valor'], $functionArgs['descricao'], $functionArgs['nome_categoria']); break;
        case 'getTarefasDoUsuario': $functionResult = getTarefasDoUsuario($pdo, $userId); break;
        case 'getTarefasUrgentes': $functionResult = getTarefasUrgentes($pdo, $userId); break;
        case 'adicionarTarefa': $functionResult = adicionarTarefa($pdo, $userId, $functionArgs['descricao']); break;
    }
    if ($functionResult !== null) {
        $conversationHistory[] = ['role' => 'model', 'parts' => [['functionCall' => ['name' => $functionName]]]];
        $conversationHistory[] = ['role' => 'tool', 'parts' => [['functionResponse' => ['name' => $functionName, 'response' => $functionResult]]]];
        $data_segunda_chamada = [ 'contents' => $conversationHistory ];
        $ch = curl_init($gemini_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_segunda_chamada));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response_string_2 = curl_exec($ch);
        $http_code_2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Verificar erro 429 na segunda chamada
        if ($http_code_2 === 429) {
            http_response_code(429);
            $rateLimitInfo = [];
            try {
                if (isset($rateLimiter) && $rateLimiter !== null) {
                    $rateLimitInfo = $rateLimiter->getUsageStats($userId, 'gemini');
                }
            } catch (Exception $e) {
                // Ignora erro ao obter stats
            }
            
            // Verificar se é erro de cota excedida
            $response_data_2 = json_decode($response_string_2, true);
            $error_message_2 = $response_data_2['error']['message'] ?? 'Limite de requisições excedido.';
            $isQuotaExceeded = stripos($error_message_2, 'quota') !== false || 
                              stripos($error_message_2, 'limit: 0') !== false ||
                              stripos($error_message_2, 'free_tier') !== false;
            
            if ($isQuotaExceeded) {
                $error_message_2 = 'A cota gratuita da API do Gemini foi excedida. Por favor, aguarde algumas horas ou considere atualizar seu plano na Google Cloud.';
            } else {
                $error_message_2 = 'Limite de requisições excedido na API do Gemini. Aguarde alguns minutos antes de tentar novamente.';
            }
            
            echo json_encode([
                'success' => false,
                'message' => $error_message_2,
                'retry_after' => 60,
                'rate_limit_info' => $rateLimitInfo,
                'quota_exceeded' => $isQuotaExceeded
            ]);
            exit();
        }
        
        $api_response_2 = json_decode($response_string_2, true);
        
        // Verificar se há erro na resposta
        if (isset($api_response_2['error'])) {
            $resposta_final_ia = 'Desculpe, ocorreu um erro ao processar sua pergunta. Tente novamente.';
        } else {
            $resposta_final_ia = $api_response_2['candidates'][0]['content']['parts'][0]['text'] ?? 'Ação concluída, mas não consegui gerar um resumo.';
            
            // Se a resposta estiver vazia ou for genérica, tentar formatar manualmente
            if (empty($resposta_final_ia) || $resposta_final_ia === 'Ação concluída, mas não consegui gerar um resumo.') {
                // Formatar resposta manualmente baseada no resultado da função
                if (isset($functionResult['tarefas_urgentes']) && !empty($functionResult['tarefas_urgentes'])) {
                    $resposta_final_ia = "Aqui estão suas tarefas mais urgentes:\n\n";
                    foreach ($functionResult['tarefas_urgentes'] as $tarefa) {
                        $data_info = '';
                        if (!empty($tarefa['data_limite'])) {
                            $data_formatada = date('d/m/Y', strtotime($tarefa['data_limite']));
                            $data_info = " (Prazo: {$data_formatada})";
                        }
                        $resposta_final_ia .= "- **{$tarefa['descricao']}** - Prioridade: {$tarefa['prioridade']}{$data_info}\n";
                    }
                } elseif (isset($functionResult['tarefas_pendentes']) && !empty($functionResult['tarefas_pendentes'])) {
                    $resposta_final_ia = "Aqui estão suas tarefas pendentes:\n\n";
                    foreach ($functionResult['tarefas_pendentes'] as $tarefa) {
                        $data_info = '';
                        if (!empty($tarefa['data_limite'])) {
                            $data_formatada = date('d/m/Y', strtotime($tarefa['data_limite']));
                            $data_info = " (Prazo: {$data_formatada})";
                        }
                        $resposta_final_ia .= "- **{$tarefa['descricao']}** - Prioridade: {$tarefa['prioridade']}{$data_info}\n";
                    }
                } elseif (isset($functionResult['resultado'])) {
                    $resposta_final_ia = $functionResult['resultado'];
                } elseif (isset($functionResult['message'])) {
                    $resposta_final_ia = $functionResult['message'];
                }
            }
        }
    }
} else { 
    // Se não houve function call, verificar se há erro
    if (isset($api_response['error'])) {
        $resposta_final_ia = 'Desculpe, ocorreu um erro ao processar sua pergunta. Tente novamente.';
    } else {
        $resposta_final_ia = $api_response['candidates'][0]['content']['parts'][0]['text'] ?? 'Não consegui entender sua pergunta. Por favor, tente reformular ou use uma das sugestões disponíveis.';
    }
}

if (!empty($resposta_final_ia)) {
    echo json_encode(['success' => true, 'resposta' => $resposta_final_ia]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A IA não retornou uma resposta válida.', 'api_debug' => $api_response]);
}
?>