<?php
// admin_bot_ia.php - Endpoint de IA para WhatsApp Bot (sem dependência de sessão)

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(120); // Aumentar tempo limite de execução para 120 segundos

// Handler de erros fatal para capturar erros antes do try-catch
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'resposta' => 'Erro fatal: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
        error_log("Fatal error em admin_bot_ia.php: " . $error['message'] . " em " . $error['file'] . ":" . $error['line']);
        exit;
    }
});

try {
    require_once 'includes/db_connect.php';
    require_once 'includes/rate_limiter.php';
    require_once 'includes/finance_helper.php';
    require_once 'includes/tasks_helper.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'resposta' => 'Erro ao carregar dependências: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("[BOT_IA] Erro ao carregar dependências: " . $e->getMessage());
    exit;
}

// Verificar se $pdo foi definido e está conectado
if (!isset($pdo) || $pdo === null) {
    if (isset($db_connect_error)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'resposta' => 'Erro de conexão com banco de dados: ' . $db_connect_error
        ], JSON_UNESCAPED_UNICODE);
        error_log("[BOT_IA] Erro de conexão com banco: " . $db_connect_error);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'resposta' => 'Erro: Banco de dados não conectado'
        ], JSON_UNESCAPED_UNICODE);
        error_log("[BOT_IA] Erro: Banco de dados não conectado");
        exit;
    }
}

// Funções de tarefas (compatíveis com processar_analise_ia.php)
function getTarefasDoUsuario(PDO $pdo, int $userId): array {
    error_log("[BOT_IA] getTarefasDoUsuario chamado com userId: $userId");
    $sql = "SELECT id, descricao, prioridade, data_limite, id_usuario,
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
    error_log("[BOT_IA] getTarefasDoUsuario encontrou " . count($tarefas) . " tarefas para userId: $userId");
    
    if (empty($tarefas)) {
        return ['resultado' => 'Você não possui tarefas pendentes.'];
    }
    
    // Buscar subtarefas para todas as tarefas
    $tarefaIds = array_column($tarefas, 'id');
    $subtarefas = [];
    if (!empty($tarefaIds)) {
        $placeholders = implode(',', array_fill(0, count($tarefaIds), '?'));
        $sqlSubtarefas = "SELECT id, id_tarefa_principal, descricao, status 
                         FROM subtarefas 
                         WHERE id_tarefa_principal IN ($placeholders)
                         ORDER BY id_tarefa_principal, id ASC";
        $stmtSubtarefas = $pdo->prepare($sqlSubtarefas);
        $stmtSubtarefas->execute($tarefaIds);
        $todasSubtarefas = $stmtSubtarefas->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapear subtarefas por tarefa principal
        foreach ($todasSubtarefas as $sub) {
            $tid = (int)$sub['id_tarefa_principal'];
            if (!isset($subtarefas[$tid])) {
                $subtarefas[$tid] = [];
            }
            $subtarefas[$tid][] = $sub;
        }
    }
    
    $resultado = "Suas tarefas pendentes:\n\n";
    foreach ($tarefas as $tarefa) {
        $data_info = '';
        if (!empty($tarefa['data_limite'])) {
            $data_formatada = date('d/m/Y', strtotime($tarefa['data_limite']));
            $data_info = " (Prazo: {$data_formatada})";
        }
        $resultado .= sprintf("- %s - Prioridade: %s%s\n", $tarefa['descricao'], $tarefa['prioridade'], $data_info);
        
        // Adicionar subtarefas se existirem
        if (isset($subtarefas[$tarefa['id']]) && !empty($subtarefas[$tarefa['id']])) {
            foreach ($subtarefas[$tarefa['id']] as $subtarefa) {
                $status_icon = $subtarefa['status'] === 'concluida' ? '✅' : '⏳';
                $resultado .= sprintf("  %s %s\n", $status_icon, $subtarefa['descricao']);
            }
        }
    }
    
    return ['resultado' => $resultado];
}

function getTarefasUrgentes(PDO $pdo, int $userId): array {
    error_log("[BOT_IA] getTarefasUrgentes chamado com userId: $userId");
    $sql = "SELECT id, descricao, prioridade, data_limite, id_usuario,
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
    error_log("[BOT_IA] getTarefasUrgentes encontrou " . count($tarefas) . " tarefas urgentes para userId: $userId");
    
    if (empty($tarefas)) {
        return ['resultado' => 'Você não possui tarefas urgentes no momento.'];
    }
    
    // Buscar subtarefas para todas as tarefas urgentes
    $tarefaIds = array_column($tarefas, 'id');
    $subtarefas = [];
    if (!empty($tarefaIds)) {
        $placeholders = implode(',', array_fill(0, count($tarefaIds), '?'));
        $sqlSubtarefas = "SELECT id, id_tarefa_principal, descricao, status 
                         FROM subtarefas 
                         WHERE id_tarefa_principal IN ($placeholders)
                         ORDER BY id_tarefa_principal, id ASC";
        $stmtSubtarefas = $pdo->prepare($sqlSubtarefas);
        $stmtSubtarefas->execute($tarefaIds);
        $todasSubtarefas = $stmtSubtarefas->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapear subtarefas por tarefa principal
        foreach ($todasSubtarefas as $sub) {
            $tid = (int)$sub['id_tarefa_principal'];
            if (!isset($subtarefas[$tid])) {
                $subtarefas[$tid] = [];
            }
            $subtarefas[$tid][] = $sub;
        }
    }
    
    $resultado = "Tarefas urgentes:\n\n";
    foreach ($tarefas as $tarefa) {
        $data_info = '';
        if (!empty($tarefa['data_limite'])) {
            $data_formatada = date('d/m/Y', strtotime($tarefa['data_limite']));
            $data_info = " (Prazo: {$data_formatada})";
        }
        $resultado .= sprintf("- %s - %s%s\n", $tarefa['descricao'], $tarefa['status_urgencia'], $data_info);
        
        // Adicionar subtarefas se existirem
        if (isset($subtarefas[$tarefa['id']]) && !empty($subtarefas[$tarefa['id']])) {
            foreach ($subtarefas[$tarefa['id']] as $subtarefa) {
                $status_icon = $subtarefa['status'] === 'concluida' ? '✅' : '⏳';
                $resultado .= sprintf("  %s %s\n", $status_icon, $subtarefa['descricao']);
            }
        }
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

function removerTarefa(PDO $pdo, int $userId, string $descricaoOuId): array {
    if (empty(trim($descricaoOuId))) {
        return ['resultado' => 'É necessário informar o ID ou descrição da tarefa a ser removida.'];
    }
    
    try {
        // Tentar buscar por ID primeiro (se for numérico)
        if (is_numeric($descricaoOuId)) {
            $stmt = $pdo->prepare("SELECT id, descricao FROM tarefas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([(int)$descricaoOuId, $userId]);
            $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Buscar por descrição (busca parcial, case-insensitive)
            $stmt = $pdo->prepare("SELECT id, descricao FROM tarefas WHERE id_usuario = ? AND descricao LIKE ? AND status = 'pendente' LIMIT 1");
            $stmt->execute([$userId, '%' . $descricaoOuId . '%']);
            $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$tarefa) {
            return ['resultado' => "Tarefa não encontrada. Verifique o ID ou descrição informada."];
        }
        
        // Deletar subtarefas primeiro (se houver)
        $stmt_sub = $pdo->prepare("DELETE FROM subtarefas WHERE id_tarefa_principal = ?");
        $stmt_sub->execute([$tarefa['id']]);
        
        // Deletar a tarefa
        $stmt_del = $pdo->prepare("DELETE FROM tarefas WHERE id = ? AND id_usuario = ?");
        $stmt_del->execute([$tarefa['id'], $userId]);
        
        if ($stmt_del->rowCount() > 0) {
            return ['resultado' => "Tarefa '{$tarefa['descricao']}' removida com sucesso!"];
        }
        return ['resultado' => 'Não foi possível remover a tarefa.'];
    } catch (PDOException $e) {
        error_log("Erro ao remover tarefa: " . $e->getMessage());
        return ['resultado' => 'Erro ao remover tarefa. Tente novamente.'];
    }
}

function atualizarTarefa(PDO $pdo, int $userId, string $descricaoOuId, string $novaDescricao = null, string $novaPrioridade = null): array {
    if (empty(trim($descricaoOuId))) {
        return ['resultado' => 'É necessário informar o ID ou descrição da tarefa a ser atualizada.'];
    }
    
    try {
        // Tentar buscar por ID primeiro (se for numérico)
        if (is_numeric($descricaoOuId)) {
            $stmt = $pdo->prepare("SELECT id, descricao, prioridade FROM tarefas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([(int)$descricaoOuId, $userId]);
            $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Buscar por descrição (busca parcial, case-insensitive)
            $stmt = $pdo->prepare("SELECT id, descricao, prioridade FROM tarefas WHERE id_usuario = ? AND descricao LIKE ? AND status = 'pendente' LIMIT 1");
            $stmt->execute([$userId, '%' . $descricaoOuId . '%']);
            $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$tarefa) {
            return ['resultado' => "Tarefa não encontrada. Verifique o ID ou descrição informada."];
        }
        
        // Montar query de atualização dinamicamente
        $updates = [];
        $params = [];
        
        if ($novaDescricao !== null && !empty(trim($novaDescricao))) {
            $updates[] = "descricao = ?";
            $params[] = trim($novaDescricao);
        }
        
        if ($novaPrioridade !== null && in_array($novaPrioridade, ['Alta', 'Média', 'Baixa'])) {
            $updates[] = "prioridade = ?";
            $params[] = $novaPrioridade;
        }
        
        if (empty($updates)) {
            return ['resultado' => 'Nenhuma alteração foi informada. Informe nova descrição ou prioridade.'];
        }
        
        $params[] = $tarefa['id'];
        $params[] = $userId;
        
        $sql = "UPDATE tarefas SET " . implode(', ', $updates) . " WHERE id = ? AND id_usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            $mensagem = "Tarefa atualizada com sucesso!";
            if ($novaDescricao) {
                $mensagem .= " Nova descrição: '{$novaDescricao}'";
            }
            if ($novaPrioridade) {
                $mensagem .= " Nova prioridade: {$novaPrioridade}";
            }
            return ['resultado' => $mensagem];
        }
        return ['resultado' => 'Nenhuma alteração foi feita na tarefa.'];
    } catch (PDOException $e) {
        error_log("Erro ao atualizar tarefa: " . $e->getMessage());
        return ['resultado' => 'Erro ao atualizar tarefa. Tente novamente.'];
    }
}

function removerTodasTarefas(PDO $pdo, int $userId): array {
    try {
        // Primeiro, contar quantas tarefas serão removidas
        $stmt_count = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
        $stmt_count->execute([$userId]);
        $count = $stmt_count->fetch(PDO::FETCH_ASSOC);
        $total = (int)$count['total'];
        
        if ($total === 0) {
            return ['resultado' => 'Você não possui tarefas pendentes para remover.'];
        }
        
        // Buscar IDs das tarefas para deletar subtarefas
        $stmt_ids = $pdo->prepare("SELECT id FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
        $stmt_ids->execute([$userId]);
        $tarefaIds = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
        
        // Deletar subtarefas primeiro
        if (!empty($tarefaIds)) {
            $placeholders = implode(',', array_fill(0, count($tarefaIds), '?'));
            $stmt_sub = $pdo->prepare("DELETE FROM subtarefas WHERE id_tarefa_principal IN ($placeholders)");
            $stmt_sub->execute($tarefaIds);
        }
        
        // Deletar todas as tarefas pendentes
        $stmt_del = $pdo->prepare("DELETE FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
        $stmt_del->execute([$userId]);
        
        $removidas = $stmt_del->rowCount();
        
        if ($removidas > 0) {
            return ['resultado' => "✅ Todas as tarefas pendentes foram removidas! ($removidas tarefa(s) removida(s))"];
        }
        return ['resultado' => 'Nenhuma tarefa foi removida.'];
    } catch (PDOException $e) {
        error_log("Erro ao remover todas as tarefas: " . $e->getMessage());
        return ['resultado' => 'Erro ao remover tarefas. Tente novamente.'];
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

// Função fallback para getallheaders() caso não esteja disponível
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Validar token
try {
    $headers = getallheaders();
    if ($headers === false) {
        $headers = [];
    }
    $token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $token);

    $expectedToken = $config['WHATSAPP_API_TOKEN'] ?? 'site-financeiro-token-2024';
    if ($token !== $expectedToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }

    // Obter dados da requisição
    $inputRaw = file_get_contents('php://input');
    if ($inputRaw === false) {
        throw new Exception('Erro ao ler dados da requisição');
    }
    $input = json_decode($inputRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
    }
    $pergunta = $input['pergunta'] ?? '';
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : null;

    error_log("[BOT_IA] Recebido - Pergunta: $pergunta, UserID: $userId");
    error_log("[BOT_IA] Input completo: " . json_encode($input));
} catch (Exception $e) {
    error_log("[BOT_IA] Erro ao processar requisição inicial: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'resposta' => 'Erro ao processar requisição: ' . $e->getMessage()
    ]);
    exit;
}

// Validar que o user_id existe na tabela usuarios
if ($userId) {
    try {
        $checkUser = $pdo->prepare("SELECT id, nome_completo, email FROM usuarios WHERE id = ?");
        $checkUser->execute([$userId]);
        $userInfo = $checkUser->fetch(PDO::FETCH_ASSOC);
        if ($userInfo) {
            error_log("[BOT_IA] Usuário validado - ID: {$userInfo['id']}, Nome: {$userInfo['nome_completo']}, Email: {$userInfo['email']}");
        } else {
            error_log("[BOT_IA] ERRO: UserID $userId não existe na tabela usuarios!");
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Usuário inválido. Faça login novamente com !login']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("[BOT_IA] Erro ao validar usuário: " . $e->getMessage());
    }
}

if (empty($pergunta) || !$userId) {
    error_log("[BOT_IA] Erro: Pergunta ou user_id vazios");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Pergunta e user_id são obrigatórios', 'debug' => ['pergunta' => $pergunta, 'user_id' => $userId]]);
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
    error_log("[BOT_IA] getResumoFinanceiro chamado com userId: $userId");
    $balance = getBalance($pdo, null, null, $userId);
    error_log("[BOT_IA] getBalance retornou: " . json_encode($balance));
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
3. getTarefasDoUsuario - Retorna todas as tarefas pendentes (incluindo subtarefas)
4. getTarefasUrgentes - Retorna apenas tarefas urgentes (alta prioridade ou próximas do prazo, incluindo subtarefas)
5. adicionarTarefa - Adiciona uma nova tarefa (parâmetro: descricao)
6. removerTarefa - Remove uma tarefa existente (parâmetro: descricaoOuId - pode ser ID numérico ou parte da descrição)
7. removerTodasTarefas - Remove TODAS as tarefas pendentes do usuário (sem parâmetros)
8. atualizarTarefa - Atualiza uma tarefa existente (parâmetros: descricaoOuId, novaDescricao opcional, novaPrioridade opcional - 'Alta', 'Média' ou 'Baixa')

IMPORTANTE SOBRE TAREFAS:
- As tarefas podem ter subtarefas associadas. Quando listar tarefas, sempre mostre as subtarefas de cada tarefa principal. Subtarefas concluídas aparecem com ✅ e pendentes com ⏳.
- Quando o usuário pedir para MODIFICAR, ATUALIZAR ou MUDAR uma tarefa, use atualizarTarefa. Exemplo: 'mude a tarefa X para Y' ou 'atualize a prioridade da tarefa Z para Alta'.
- Quando o usuário pedir para REMOVER, DELETAR, EXCLUIR ou APAGAR uma tarefa específica, use removerTarefa. Você pode buscar por ID ou por parte da descrição.
- Quando o usuário pedir para REMOVER/DELETAR/EXCLUIR/APAGAR TODAS as tarefas (ex: 'apague todas', 'delete todas', 'remova todas'), use removerTodasTarefas.
- Se o usuário mencionar 'prioridade máxima', interprete como prioridade 'Alta'.

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
                    'properties' => (object)[]  // Objeto vazio, não array
                ]
            ],
            [
                'name' => 'getPrincipaisCategoriasGasto',
                'description' => 'Obtém as 5 principais categorias de gasto do mês atual',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[]  // Objeto vazio, não array
                ]
            ],
            [
                'name' => 'getTarefasDoUsuario',
                'description' => 'Obtém todas as tarefas pendentes do usuário',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[]  // Objeto vazio, não array
                ]
            ],
            [
                'name' => 'getTarefasUrgentes',
                'description' => 'Obtém apenas tarefas urgentes (alta prioridade ou próximas do prazo)',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[]  // Objeto vazio, não array
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
            ],
            [
                'name' => 'removerTarefa',
                'description' => 'Remove uma tarefa existente. Pode buscar por ID numérico ou por parte da descrição',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'descricaoOuId' => [
                            'type' => 'STRING',
                            'description' => 'ID numérico da tarefa ou parte da descrição da tarefa a ser removida'
                        ]
                    ],
                    'required' => ['descricaoOuId']
                ]
            ],
            [
                'name' => 'atualizarTarefa',
                'description' => 'Atualiza uma tarefa existente. Pode alterar descrição e/ou prioridade',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'descricaoOuId' => [
                            'type' => 'STRING',
                            'description' => 'ID numérico da tarefa ou parte da descrição da tarefa a ser atualizada'
                        ],
                        'novaDescricao' => [
                            'type' => 'STRING',
                            'description' => 'Nova descrição para a tarefa (opcional)'
                        ],
                        'novaPrioridade' => [
                            'type' => 'STRING',
                            'description' => 'Nova prioridade: "Alta", "Média" ou "Baixa" (opcional)'
                        ]
                    ],
                    'required' => ['descricaoOuId']
                ]
            ],
            [
                'name' => 'removerTodasTarefas',
                'description' => 'Remove TODAS as tarefas pendentes do usuário. Use quando o usuário pedir para apagar/deletar/remover todas as tarefas',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[]  // Objeto vazio, não array
                ]
            ]
        ]
    ]
];

// Verificar se GEMINI_API_KEY está definido
if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
    error_log("[BOT_IA] ERRO: GEMINI_API_KEY não está definido");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'resposta' => 'Erro de configuração: API Key do Gemini não encontrada.'
    ]);
    exit;
}

try {
    // Chamar Gemini API - usar modelo recomendado gemini-2.5-flash
    // Tentar primeiro gemini-2.5-flash, depois gemini-2.5-pro se necessário
    $models = ['gemini-2.5-flash', 'gemini-2.5-pro'];
    $currentModel = $models[0];
    $gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $currentModel . ':generateContent?key=' . GEMINI_API_KEY;
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
    
    // Garantir que properties vazios sejam objetos, não arrays
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Substituir arrays vazios [] por objetos {} nas properties
    $jsonData = preg_replace('/"properties":\s*\[\]/', '"properties":{}', $jsonData);
    
    error_log("[BOT_IA] Enviando para Gemini - UserID: $userId, Modelo: $currentModel");
    error_log("[BOT_IA] JSON preview: " . substr($jsonData, 0, 500));
    
    $ch = curl_init($gemini_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response_string = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception("Erro cURL: $curl_error");
    }
    
    if ($http_code === 429) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'resposta' => 'Limite de requisições excedido. Aguarde alguns minutos.'
        ]);
        exit;
    }
    
    // Se erro 404, tentar modelo alternativo
    if ($http_code === 404 && $currentModel === $models[0]) {
        error_log("[BOT_IA] Modelo $currentModel não disponível, tentando " . $models[1]);
        $currentModel = $models[1];
        $gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $currentModel . ':generateContent?key=' . GEMINI_API_KEY;
        
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
    }
    
    if ($http_code !== 200) {
        $error_details = '';
        $error_message_full = '';
        if ($response_string) {
            $error_data = json_decode($response_string, true);
            if (isset($error_data['error']['message'])) {
                $error_details = ': ' . $error_data['error']['message'];
                $error_message_full = $error_data['error']['message'];
            } else {
                $error_details = ': ' . substr($response_string, 0, 200);
                $error_message_full = substr($response_string, 0, 200);
            }
        }
        error_log("[BOT_IA] HTTP Error $http_code$error_details");
        
        // Se for erro 400, tentar sem tools (pode ser que o modelo não suporte)
        if ($http_code === 400 && $currentModel === $models[0]) {
            error_log("[BOT_IA] Tentando sem tools devido ao erro 400");
            $data_simple = [
                'contents' => $conversationHistory
            ];
            
            $jsonSimple = json_encode($data_simple, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            $ch = curl_init($gemini_api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonSimple);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response_string = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                // Sucesso sem tools, continuar processamento
                $api_response = json_decode($response_string, true);
                if ($api_response && isset($api_response['candidates'][0]['content']['parts'][0]['text'])) {
                    $resposta_final = $api_response['candidates'][0]['content']['parts'][0]['text'];
                    echo json_encode(['success' => true, 'resposta' => $resposta_final]);
                    exit;
                }
            } else {
                // Ainda deu erro, retornar mensagem completa
                throw new Exception("Erro HTTP $http_code da API Gemini$error_details");
            }
        } else {
            // Para outros erros ou se já tentou sem tools, retornar erro completo
            throw new Exception("Erro HTTP $http_code da API Gemini$error_details");
        }
    }
    
    $api_response = json_decode($response_string, true);
    
    if (!$api_response) {
        error_log("[BOT_IA] Resposta inválida do Gemini: " . substr($response_string, 0, 500));
        throw new Exception("Resposta inválida da API Gemini");
    }
    
    $resposta_final = '';
    
    if (isset($api_response['error'])) {
        $error_msg = $api_response['error']['message'] ?? 'Erro desconhecido';
        error_log("[BOT_IA] Erro da API: $error_msg");
        $resposta_final = 'Desculpe, ocorreu um erro ao processar sua pergunta: ' . $error_msg;
    } else {
        // Verificar se há candidates
        if (!isset($api_response['candidates']) || empty($api_response['candidates'])) {
            error_log("[BOT_IA] Nenhum candidate na resposta");
            throw new Exception("Resposta da API sem candidates");
        }
        
        $candidate = $api_response['candidates'][0];
        if (!isset($candidate['content']['parts']) || empty($candidate['content']['parts'])) {
            error_log("[BOT_IA] Candidate sem parts");
            throw new Exception("Candidate sem parts");
        }
        
        $functionCall = $candidate['content']['parts'][0]['functionCall'] ?? null;
        
        if ($functionCall) {
            $functionName = $functionCall['name'] ?? '';
            $functionArgs = $functionCall['args'] ?? [];
            
            if (empty($functionName)) {
                throw new Exception("FunctionCall sem nome");
            }
            
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
                            // Converter formato de resposta
                            if (isset($result['message'])) {
                                $result = ['resultado' => $result['message']];
                            } elseif (isset($result['success']) && !$result['success']) {
                                $result = ['resultado' => $result['message'] ?? 'Erro ao adicionar tarefa.'];
                            }
                        } else {
                            $result = ['resultado' => 'Descrição da tarefa é obrigatória.'];
                        }
                        break;
                    case 'removerTarefa':
                        $descricaoOuId = $functionArgs['descricaoOuId'] ?? '';
                        if ($descricaoOuId) {
                            $result = removerTarefa($pdo, $userId, $descricaoOuId);
                        } else {
                            $result = ['resultado' => 'É necessário informar o ID ou descrição da tarefa a ser removida.'];
                        }
                        break;
                    case 'atualizarTarefa':
                        $descricaoOuId = $functionArgs['descricaoOuId'] ?? '';
                        $novaDescricao = $functionArgs['novaDescricao'] ?? null;
                        $novaPrioridade = $functionArgs['novaPrioridade'] ?? null;
                        if ($descricaoOuId) {
                            $result = atualizarTarefa($pdo, $userId, $descricaoOuId, $novaDescricao, $novaPrioridade);
                        } else {
                            $result = ['resultado' => 'É necessário informar o ID ou descrição da tarefa a ser atualizada.'];
                        }
                        break;
                    case 'removerTodasTarefas':
                        $result = removerTodasTarefas($pdo, $userId);
                        break;
                    default:
                        $result = ['resultado' => 'Função não reconhecida: ' . $functionName];
                }
                // Enviar resultado de volta para a IA
                // Adicionar a chamada da função e a resposta ao histórico
                $conversationHistory[] = [
                    'role' => 'model',
                    'parts' => [['functionCall' => $functionCall]]
                ];
                
                $conversationHistory[] = [
                    'role' => 'function', 
                    'parts' => [['functionResponse' => [
                        'name' => $functionName,
                        'response' => ['content' => $result]
                    ]]]
                ];
                
                // Fazer segunda chamada para a IA processar o resultado
                error_log("[BOT_IA] Iniciando segunda chamada para a API (Function Response)...");
                $data2 = [
                    'contents' => $conversationHistory,
                    'tools' => $tools // Manter ferramentas disponíveis caso queira chamar outra
                ];
                
                // Garantir que properties vazios sejam objetos, não arrays
                $jsonData2 = json_encode($data2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $jsonData2 = preg_replace('/"properties":\s*\[\]/', '"properties":{}', $jsonData2);
                
                $ch2 = curl_init($gemini_api_url);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $jsonData2);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 60); // Timeout aumentado para segunda chamada
                $response_string2 = curl_exec($ch2);
                $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $curl_error2 = curl_error($ch2);
                curl_close($ch2);
                
                if ($curl_error2) {
                    error_log("[BOT_IA] Erro cURL na segunda chamada: $curl_error2");
                }
                
                error_log("[BOT_IA] Resposta da segunda chamada: HTTP $http_code2");

                if ($http_code2 === 200) {
                    $api_response2 = json_decode($response_string2, true);
                    if (isset($api_response2['candidates'][0]['content']['parts'][0]['text'])) {
                        $resposta_final = $api_response2['candidates'][0]['content']['parts'][0]['text'];
                    } else {
                        // Fallback se a IA não gerar texto após a função
                        $resposta_final = $result['resultado'] ?? json_encode($result);
                    }
                } else {
                     error_log("[BOT_IA] Erro na segunda chamada: HTTP $http_code2");
                     // Fallback para o resultado cru
                     $resposta_final = $result['resultado'] ?? json_encode($result);
                }

            } catch (Exception $e) {
                error_log("[BOT_IA] Erro ao executar função $functionName: " . $e->getMessage());
                error_log("[BOT_IA] Stack: " . $e->getTraceAsString());
                $resposta_final = 'Erro ao processar sua solicitação: ' . $e->getMessage();
            }
        } else {
            // Resposta direta da IA
            $text = $candidate['content']['parts'][0]['text'] ?? null;
            if ($text) {
                $resposta_final = $text;
            } else {
                error_log("[BOT_IA] Nenhum texto ou functionCall na resposta");
                $resposta_final = 'Não foi possível gerar uma resposta. Tente reformular sua pergunta.';
            }
        }
    }
} catch (Exception $e) {
    error_log("[BOT_IA] Exception: " . $e->getMessage());
    error_log("[BOT_IA] Stack: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'resposta' => 'Erro ao processar: ' . $e->getMessage()
    ]);
    exit;
}

// O registro de uso já é feito automaticamente no checkRateLimit()
// Não é necessário registrar novamente aqui

echo json_encode([
    'success' => true,
    'resposta' => $resposta_final
]);

