<?php
// admin_bot_ia.php - Endpoint de IA para WhatsApp Bot (sem dependência de sessão)

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';
require_once 'includes/finance_helper.php';
require_once 'includes/tasks_helper.php';

// Funções de tarefas (compatíveis com processar_analise_ia.php)
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
                data_limite ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tarefas)) {
        return ['resultado' => 'Você não possui tarefas pendentes.'];
    }
    
    $resultado = "Suas tarefas pendentes:\n\n";
    foreach ($tarefas as $tarefa) {
        $data_info = '';
        if (!empty($tarefa['data_limite'])) {
            $data_formatada = date('d/m/Y', strtotime($tarefa['data_limite']));
            $data_info = " (Prazo: {$data_formatada})";
        }
        $resultado .= sprintf("- %s - Prioridade: %s%s\n", $tarefa['descricao'], $tarefa['prioridade'], $data_info);
    }
    
    return ['resultado' => $resultado];
}

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
        return ['resultado' => 'Você não possui tarefas urgentes no momento.'];
    }
    
    $resultado = "Tarefas urgentes:\n\n";
    foreach ($tarefas as $tarefa) {
        $data_info = '';
        if (!empty($tarefa['data_limite'])) {
            $data_formatada = date('d/m/Y', strtotime($tarefa['data_limite']));
            $data_info = " (Prazo: {$data_formatada})";
        }
        $resultado .= sprintf("- %s - %s%s\n", $tarefa['descricao'], $tarefa['status_urgencia'], $data_info);
    }
    
    return ['resultado' => $resultado];
}

function adicionarTarefa(PDO $pdo, int $userId, string $descricao): array {
    if (empty(trim($descricao))) {
        return ['resultado' => 'A descrição da tarefa não pode estar vazia.'];
    }
    
    try {
        $stmt_ordem = $pdo->prepare("SELECT MAX(ordem) as max_ordem FROM tarefas WHERE id_usuario = ?");
        $stmt_ordem->execute([$userId]);
        $max_ordem = $stmt_ordem->fetchColumn();
        $nova_ordem = ($max_ordem === null) ? 0 : $max_ordem + 1;
        
        $sql = "INSERT INTO tarefas (id_usuario, descricao, status, data_criacao, prioridade, ordem) 
                VALUES (?, ?, 'pendente', NOW(), 'Média', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $descricao, $nova_ordem]);
        
        if ($stmt->rowCount() > 0) {
            return ['resultado' => "Tarefa '{$descricao}' adicionada com sucesso!"];
        }
        return ['resultado' => 'Não foi possível adicionar a tarefa.'];
    } catch (PDOException $e) {
        error_log("Erro ao adicionar tarefa: " . $e->getMessage());
        return ['resultado' => 'Erro ao adicionar tarefa. Tente novamente.'];
    }
}

// Carregar configuração
$config = [];
try {
    $configFile = __DIR__ . '/config.json';
    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);
        $config = json_decode($configContent, true);
    }
} catch (Exception $e) {
    error_log("Erro ao carregar config: " . $e->getMessage());
}

// Validar token
$headers = getallheaders();
$token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

$expectedToken = $config['WHATSAPP_API_TOKEN'] ?? 'site-financeiro-token-2024';
if ($token !== $expectedToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true);
$pergunta = $input['pergunta'] ?? '';
$userId = isset($input['user_id']) ? (int)$input['user_id'] : null;

if (empty($pergunta) || !$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Pergunta e user_id são obrigatórios']);
    exit;
}

// Verificar rate limiting
try {
    $rateLimiter = new RateLimiter($pdo);
    $rateLimitCheck = $rateLimiter->checkRateLimit($userId, 'gemini');
    
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => $rateLimitCheck['message'],
            'retry_after' => $rateLimitCheck['retry_after']
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Rate Limiter Error: " . $e->getMessage());
}

// Funções disponíveis para a IA
function getResumoFinanceiro(PDO $pdo, int $userId): array {
    $balance = getBalance($pdo, null, null, $userId);
    if (!$balance['success']) {
        return ['resultado' => 'Erro ao obter resumo financeiro.'];
    }
    
    $mes = date('m');
    $ano = date('Y');
    return [
        'resultado' => sprintf(
            "Resumo Financeiro (%s/%s):\n\n" .
            "💰 Receitas: R$ %s (%d transações)\n" .
            "💸 Despesas: R$ %s (%d transações)\n" .
            "💵 Saldo: R$ %s",
            $mes, $ano,
            number_format($balance['receitas']['total'], 2, ',', '.'),
            $balance['receitas']['count'],
            number_format($balance['despesas']['total'], 2, ',', '.'),
            $balance['despesas']['count'],
            number_format($balance['saldo'], 2, ',', '.')
        )
    ];
}

function getPrincipaisCategoriasGasto(PDO $pdo, int $userId): array {
    $sql = "SELECT cat.nome, SUM(t.valor) as total 
            FROM transacoes t
            JOIN categorias cat ON t.id_categoria = cat.id
            WHERE t.id_usuario = ? AND t.tipo = 'despesa' 
            AND MONTH(t.data_transacao) = MONTH(CURDATE())
            AND YEAR(t.data_transacao) = YEAR(CURDATE())
            GROUP BY cat.nome
            ORDER BY total DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categorias)) {
        return ['resultado' => 'Nenhuma despesa registrada este mês.'];
    }
    
    $resultado = "Principais categorias de gasto este mês:\n\n";
    foreach ($categorias as $cat) {
        $resultado .= sprintf("• %s: R$ %s\n", $cat['nome'], number_format($cat['total'], 2, ',', '.'));
    }
    
    return ['resultado' => $resultado];
}

// Prompt para a IA
$prompt_inicial = "Você é um assistente financeiro especializado em ajudar usuários a gerenciar suas finanças e tarefas através do WhatsApp. 

Você tem acesso às seguintes ferramentas:
1. getResumoFinanceiro - Retorna receitas, despesas e saldo do mês atual
2. getPrincipaisCategoriasGasto - Retorna as 5 categorias com mais gastos no mês
3. getTarefasDoUsuario - Retorna todas as tarefas pendentes
4. getTarefasUrgentes - Retorna apenas tarefas urgentes (alta prioridade ou próximas do prazo)
5. adicionarTarefa - Adiciona uma nova tarefa

SEMPRE use uma ferramenta primeiro quando o usuário perguntar sobre dados financeiros ou tarefas. Depois, formule uma resposta clara e objetiva baseada no resultado.

Seja conciso e direto nas respostas, formatando números monetários em R$ e datas em formato brasileiro (dd/mm/aaaa).";

$tools = [
    [
        'functionDeclarations' => [
            [
                'name' => 'getResumoFinanceiro',
                'description' => 'Obtém resumo financeiro do mês atual (receitas, despesas, saldo)',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => []
                ]
            ],
            [
                'name' => 'getPrincipaisCategoriasGasto',
                'description' => 'Obtém as 5 principais categorias de gasto do mês atual',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => []
                ]
            ],
            [
                'name' => 'getTarefasDoUsuario',
                'description' => 'Obtém todas as tarefas pendentes do usuário',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => []
                ]
            ],
            [
                'name' => 'getTarefasUrgentes',
                'description' => 'Obtém apenas tarefas urgentes (alta prioridade ou próximas do prazo)',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => []
                ]
            ],
            [
                'name' => 'adicionarTarefa',
                'description' => 'Adiciona uma nova tarefa. Parâmetro: descricao (string)',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'descricao' => [
                            'type' => 'STRING',
                            'description' => 'Descrição da tarefa a ser adicionada'
                        ]
                    ],
                    'required' => ['descricao']
                ]
            ]
        ]
    ]
];

// Chamar Gemini API
$gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . GEMINI_API_KEY;
$conversationHistory = [
    ['role' => 'user', 'parts' => [['text' => $prompt_inicial]]],
    ['role' => 'model', 'parts' => [['text' => 'Entendido! Estou pronto para ajudar.']]],
    ['role' => 'user', 'parts' => [['text' => $pergunta]]]
];
$data = [
    'contents' => $conversationHistory,
    'tools' => $tools,
    'tool_config' => [
        'function_calling_config' => [
            'mode' => 'ANY'
        ]
    ]
];

$ch = curl_init($gemini_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$response_string = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 429) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Limite de requisições excedido. Aguarde alguns minutos.'
    ]);
    exit;
}

$api_response = json_decode($response_string, true);
$resposta_final = '';

if (isset($api_response['error'])) {
    $resposta_final = 'Desculpe, ocorreu um erro ao processar sua pergunta. Tente novamente.';
} else {
    $functionCall = $api_response['candidates'][0]['content']['parts'][0]['functionCall'] ?? null;
    
    if ($functionCall) {
        $functionName = $functionCall['name'];
        $functionArgs = $functionCall['args'] ?? [];
        
        try {
            switch ($functionName) {
                case 'getResumoFinanceiro':
                    $result = getResumoFinanceiro($pdo, $userId);
                    break;
                case 'getPrincipaisCategoriasGasto':
                    $result = getPrincipaisCategoriasGasto($pdo, $userId);
                    break;
                case 'getTarefasDoUsuario':
                    $result = getTarefasDoUsuario($pdo, $userId);
                    break;
                case 'getTarefasUrgentes':
                    $result = getTarefasUrgentes($pdo, $userId);
                    break;
                case 'adicionarTarefa':
                    $descricao = $functionArgs['descricao'] ?? '';
                    if ($descricao) {
                        $result = adicionarTarefa($pdo, $userId, $descricao);
                    } else {
                        $result = ['resultado' => 'Descrição da tarefa é obrigatória.'];
                    }
                    break;
                default:
                    $result = ['resultado' => 'Função não reconhecida.'];
            }
            
            $resposta_final = $result['resultado'] ?? 'Resposta não disponível.';
        } catch (Exception $e) {
            error_log("Erro ao executar função: " . $e->getMessage());
            $resposta_final = 'Erro ao processar sua solicitação. Tente novamente.';
        }
    } else {
        // Resposta direta da IA
        $resposta_final = $api_response['candidates'][0]['content']['parts'][0]['text'] ?? 'Não foi possível gerar uma resposta.';
    }
}

// Registrar uso no rate limiter
try {
    if (isset($rateLimiter)) {
        $rateLimiter->recordUsage($userId, 'gemini');
    }
} catch (Exception $e) {
    error_log("Erro ao registrar uso: " . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'resposta' => $resposta_final
]);

