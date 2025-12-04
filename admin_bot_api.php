<?php
// admin_bot_api.php - API para processar comandos do bot WhatsApp

// Habilitar exibição de erros para debug (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir no output, mas logar
ini_set('log_errors', 1);

// Handler de erros fatal para capturar erros antes do try-catch
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro fatal PHP: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
        error_log("Fatal error em admin_bot_api.php: " . $error['message'] . " em " . $error['file'] . ":" . $error['line']);
        exit;
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Habilitar exibição de erros apenas para debug (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir na tela, apenas logar
ini_set('log_errors', 1);

try {
    require_once 'includes/db_connect.php';
    require_once 'includes/finance_helper.php';
    require_once 'includes/tasks_helper.php';
    require_once 'includes/command_helper.php';
    
    // Verificar se $pdo foi definido e está conectado
    if (!isset($pdo) || $pdo === null) {
        if (isset($db_connect_error)) {
            throw new Exception("Erro de conexão com banco de dados: " . $db_connect_error);
        } else {
            throw new Exception("Conexão com banco de dados não foi estabelecida");
        }
    }
    
    // Testar conexão
    try {
        $pdo->query("SELECT 1");
    } catch (PDOException $e) {
        throw new Exception("Erro ao testar conexão com banco: " . $e->getMessage());
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar arquivos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log("Erro ao carregar arquivos em admin_bot_api.php: " . $e->getMessage());
    exit;
}

// Carregar configuração
$config = [];
try {
    $configFile = __DIR__ . '/config.json';
    if (!file_exists($configFile)) {
        throw new Exception("Arquivo config.json não encontrado em: " . $configFile);
    }
    $configContent = file_get_contents($configFile);
    if ($configContent === false) {
        throw new Exception("Erro ao ler config.json");
    }
    $config = json_decode($configContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar config.json: " . json_last_error_msg());
    }
    if (!$config || !is_array($config)) {
        throw new Exception("config.json está vazio ou inválido");
    }
} catch (Exception $e) {
    error_log("Erro ao carregar configuração: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erro ao carregar configuração: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para formatar valores monetários
if (!function_exists('formatMoney')) {
    function formatMoney(float $value): string {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

// Validar token
$headers = getallheaders();
$token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

// Debug: log do token recebido (apenas primeiros caracteres por segurança)
error_log("Token recebido: " . substr($token, 0, 10) . "...");
error_log("Token esperado: " . substr($config['WHATSAPP_API_TOKEN'], 0, 10) . "...");

if ($token !== $config['WHATSAPP_API_TOKEN']) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Token inválido',
        'debug' => [
            'token_recebido_length' => strlen($token),
            'token_esperado_length' => strlen($config['WHATSAPP_API_TOKEN']),
            'token_recebido_preview' => substr($token, 0, 10) . '...',
            'token_esperado_preview' => substr($config['WHATSAPP_API_TOKEN'], 0, 10) . '...'
        ]
    ]);
    exit;
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);
$phoneNumber = $input['phone'] ?? '';
$command = $input['command'] ?? '';
$args = $input['args'] ?? [];
$message = $input['message'] ?? '';

// Normalizar número de telefone
function normalizePhone(string $phone): string {
    $phone = preg_replace('/\D+/', '', $phone);
    if (strlen($phone) === 11 && substr($phone, 0, 2) !== '55') {
        return '55' . $phone;
    }
    return $phone;
}

$phoneNormalized = normalizePhone($phoneNumber);

// Debug: log do número normalizado
error_log("admin_bot_api: Número original: " . $phoneNumber);
error_log("admin_bot_api: Número normalizado: " . $phoneNormalized);

// Verificar se é admin
$isAdmin = false;
if (isset($config['ADMIN_WHATSAPP_NUMBERS']) && is_array($config['ADMIN_WHATSAPP_NUMBERS'])) {
    foreach ($config['ADMIN_WHATSAPP_NUMBERS'] as $adminNum) {
        if (normalizePhone($adminNum) === $phoneNormalized) {
            $isAdmin = true;
            break;
        }
    }
}

// Função para obter usuário logado via WhatsApp
function getWhatsAppUser(PDO $pdo, string $phone): ?array {
    try {
        // Verificar se tabela existe
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'whatsapp_sessions'");
            if ($checkTable->rowCount() === 0) {
                error_log("getWhatsAppUser: Tabela whatsapp_sessions não existe");
                return null;
            }
        } catch (PDOException $e) {
            error_log("getWhatsAppUser: Erro ao verificar tabela: " . $e->getMessage());
            return null;
        }
        
        // Debug: log do número sendo buscado
        error_log("getWhatsAppUser: Buscando sessão para telefone: " . $phone);
        
        $sql = "SELECT ws.user_id, u.id, u.nome_completo as nome, u.email, u.tipo 
                FROM whatsapp_sessions ws 
                JOIN usuarios u ON ws.user_id = u.id 
                WHERE ws.phone_number = ? AND ws.is_active = 1 
                ORDER BY ws.last_activity DESC 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: log do resultado
        if ($user) {
            error_log("getWhatsAppUser: Sessão encontrada para usuário ID: " . $user['id']);
            // Atualizar última atividade
            try {
                $updateStmt = $pdo->prepare("UPDATE whatsapp_sessions SET last_activity = NOW() WHERE phone_number = ? AND is_active = 1");
                $updateStmt->execute([$phone]);
            } catch (PDOException $e) {
                error_log("Erro ao atualizar última atividade: " . $e->getMessage());
                // Continuar mesmo se falhar a atualização
            }
        } else {
            error_log("getWhatsAppUser: Nenhuma sessão ativa encontrada para telefone: " . $phone);
        }
        
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Erro ao buscar usuário WhatsApp: " . $e->getMessage());
        return null;
    } catch (Exception $e) {
        error_log("Erro geral em getWhatsAppUser: " . $e->getMessage());
        return null;
    }
}

// Função para fazer login via WhatsApp
function loginWhatsApp(PDO $pdo, string $phone, string $email, string $password): array {
    try {
        // Buscar usuário por email ou nome de usuário
        // Verificar quais colunas existem na tabela
        $stmtCheck = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'senha%'");
        $senhaColumns = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
        
        // Montar SELECT dinamicamente baseado nas colunas disponíveis
        $selectFields = "id, nome_completo as nome, email, usuario";
        if (in_array('senha_hash', $senhaColumns)) {
            $selectFields .= ", senha_hash";
        }
        if (in_array('senha', $senhaColumns)) {
            $selectFields .= ", senha";
        }
        
        $stmt = $pdo->prepare("SELECT $selectFields FROM usuarios WHERE email = ? OR usuario = ? LIMIT 1");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => '❌ Email/usuário não encontrado.'];
        }
        
        // Verificar senha (suporta senha_hash e senha para compatibilidade)
        $senhaValida = false;
        
        // Prioridade: senha_hash (campo padrão do sistema)
        if (isset($user['senha_hash']) && !empty($user['senha_hash']) && password_verify($password, $user['senha_hash'])) {
            $senhaValida = true;
        }
        // Fallback: campo senha (compatibilidade)
        elseif (isset($user['senha']) && !empty($user['senha'])) {
            // Se a senha está em hash
            if (password_verify($password, $user['senha'])) {
                $senhaValida = true;
            }
            // Se a senha está em texto simples ou MD5 (compatibilidade)
            elseif ($user['senha'] === $password || md5($password) === $user['senha']) {
                $senhaValida = true;
            }
        }
        
        if (!$senhaValida) {
            return ['success' => false, 'message' => '❌ Senha incorreta.'];
        }
        
        // Criar ou atualizar sessão
        $sql = "INSERT INTO whatsapp_sessions (phone_number, user_id, logged_in_at, last_activity, is_active) 
                VALUES (?, ?, NOW(), NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                    user_id = VALUES(user_id),
                    logged_in_at = NOW(),
                    last_activity = NOW(),
                    is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$phone, $user['id']]);
        
        // Debug: verificar se a sessão foi criada/atualizada
        error_log("loginWhatsApp: Sessão criada/atualizada para telefone: " . $phone . ", user_id: " . $user['id']);
        $verifyStmt = $pdo->prepare("SELECT phone_number, user_id, is_active FROM whatsapp_sessions WHERE phone_number = ?");
        $verifyStmt->execute([$phone]);
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        if ($verifyResult) {
            error_log("loginWhatsApp: Verificação - Sessão confirmada. is_active: " . $verifyResult['is_active']);
        } else {
            error_log("loginWhatsApp: ERRO - Sessão não foi criada!");
        }
        
        return [
            'success' => true,
            'message' => "✅ *Login realizado com sucesso!*\n\n" .
                        "Bem-vindo, " . $user['nome'] . "!\n" .
                        "Sua conta está conectada ao WhatsApp.\n\n" .
                        "Digite !menu para ver os comandos disponíveis."
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => '❌ Erro ao fazer login: ' . $e->getMessage()];
    }
}

// Função para fazer logout
function logoutWhatsApp(PDO $pdo, string $phone): array {
    try {
        $stmt = $pdo->prepare("UPDATE whatsapp_sessions SET is_active = 0 WHERE phone_number = ?");
        $stmt->execute([$phone]);
        
        return [
            'success' => true,
            'message' => "✅ *Logout realizado com sucesso!*\n\n" .
                        "Sua sessão foi encerrada.\n" .
                        "Use !login para conectar novamente."
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => '❌ Erro ao fazer logout: ' . $e->getMessage()];
    }
}

// Obter usuário logado
$loggedUser = null;
$userId = null;
try {
    $loggedUser = getWhatsAppUser($pdo, $phoneNormalized);
    $userId = $loggedUser ? (int)$loggedUser['id'] : null;
} catch (Exception $e) {
    error_log("Erro ao obter usuário logado: " . $e->getMessage());
    // Continuar sem usuário logado
}

// Função de log
function writeLog(PDO $pdo, string $phone, string $command, string $message, string $response, bool $success): void {
    try {
        $sql = "INSERT INTO whatsapp_bot_logs (phone_number, command, message, response, success) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$phone, $command, $message, $response, $success ? 1 : 0]);
    } catch (PDOException $e) {
        error_log("Erro ao salvar log: " . $e->getMessage());
    }
}

// Processar comandos
$response = ['success' => false, 'message' => 'Comando não reconhecido'];

try {
    switch ($command) {
        case '!login':
            if (count($args) < 2) {
                $response = ['success' => false, 'message' => '❌ Uso: !login EMAIL SENHA\n\nExemplo: !login usuario@email.com minhasenha123'];
                break;
            }
            $email = $args[0];
            $password = $args[1];
            $response = loginWhatsApp($pdo, $phoneNormalized, $email, $password);
            // Recalcular usuário logado após login bem-sucedido
            if ($response['success']) {
                $loggedUser = getWhatsAppUser($pdo, $phoneNormalized);
                $userId = $loggedUser ? (int)$loggedUser['id'] : null;
            }
            break;

        case '!logout':
            $response = logoutWhatsApp($pdo, $phoneNormalized);
            // Recalcular usuário logado após logout
            if ($response['success']) {
                $loggedUser = null;
                $userId = null;
            }
            break;

        case '!status':
            // Recalcular usuário logado para garantir que está atualizado
            $loggedUser = getWhatsAppUser($pdo, $phoneNormalized);
            $userId = $loggedUser ? (int)$loggedUser['id'] : null;
            
            if ($loggedUser) {
                $response = [
                    'success' => true,
                    'message' => "✅ *Você está logado!*\n\n" .
                               "👤 Nome: " . $loggedUser['nome'] . "\n" .
                               "📧 Email: " . $loggedUser['email'] . "\n" .
                               "🆔 ID: #" . $loggedUser['id'] . "\n" .
                               "📱 Telefone: " . $phoneNormalized . "\n\n" .
                               "Todas as transações serão associadas à sua conta."
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => "⚠️ *Você não está logado!*\n\n" .
                               "Para usar os comandos, faça login primeiro:\n" .
                               "!login EMAIL SENHA\n\n" .
                               "Exemplo: !login usuario@email.com minhasenha123"
                ];
            }
            break;

        case '!menu':
        case '!help':
        case '!ajuda':
        case '/menu':
        case '/help':
            try {
                error_log("[MENU] Iniciando processamento do menu para: " . $phoneNormalized);
                
                // Recalcular usuário logado para garantir que está atualizado
                $loggedUser = getWhatsAppUser($pdo, $phoneNormalized);
                error_log("[MENU] Usuário obtido: " . ($loggedUser ? "SIM (ID: " . $loggedUser['id'] . ")" : "NÃO"));
                
                $userId = $loggedUser ? (int)$loggedUser['id'] : null;
            } catch (Exception $e) {
                error_log("Erro ao obter usuário no menu: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $loggedUser = null;
                $userId = null;
            }
            
            $nomeUsuario = '';
            try {
                if ($loggedUser && isset($loggedUser['nome'])) {
                    $nomeUsuario = $loggedUser['nome'];
                }
                error_log("[MENU] Nome do usuário: " . ($nomeUsuario ?: "Não definido"));
            } catch (Exception $e) {
                error_log("[MENU] Erro ao obter nome do usuário: " . $e->getMessage());
            }
            
            $response = [
                'success' => true,
                'message' => "📋 *MENU DE COMANDOS*\n\n" .
                           ($loggedUser && $nomeUsuario ? "✅ Logado como: " . $nomeUsuario . "\n\n" : "⚠️ *Você não está logado!*\nUse: !login EMAIL SENHA\n\n") .
                           "*AUTENTICAÇÃO*\n" .
                           "🔐 !login EMAIL SENHA\n" .
                           "🚪 !logout\n" .
                           "ℹ️ !status\n\n" .
                           "*FINANCEIRO*\n" .
                           "💰 !receita VALOR DESCRIÇÃO\n" .
                           "💸 !despesa VALOR DESCRIÇÃO\n" .
                           "💵 !saldo\n" .
                           "📊 !extrato\n" .
                           "📈 !relatorio\n" .
                           "📊 !dashboard\n" .
                           "📊 !semana (resumo semanal)\n" .
                           "📊 !comparar (comparar meses)\n" .
                           "🗑️ !deletar ID\n\n" .
                           "*TAREFAS*\n" .
                           "📋 !tarefas\n" .
                           "➕ !addtarefa DESCRIÇÃO\n" .
                           "✅ !concluir ID\n" .
                           "🚨 !urgentes\n" .
                           "📅 !tarefahoje\n" .
                           "📊 !estatisticas\n\n" .
                           "*CLIENTES*\n" .
                           "👤 !clientes\n" .
                           "⚠️ !pendencias\n\n" .
                           "💡 Digite !ajuda COMANDO para mais detalhes\n" .
                           "💡 Exemplo: !ajuda receita"
            ];
            break;
        
        case '!ajuda':
            // Ajuda contextual para comandos específicos (só se não for menu/help)
            if (count($args) > 0) {
                $helpCommand = '!' . strtolower($args[0]);
                if (function_exists('formatHelpMessage')) {
                    try {
                        $helpMsg = formatHelpMessage($helpCommand, $loggedUser);
                        $response = ['success' => true, 'message' => $helpMsg];
                    } catch (Exception $e) {
                        error_log("Erro ao formatar ajuda: " . $e->getMessage());
                        $response = [
                            'success' => true,
                            'message' => "💡 *AJUDA: " . strtoupper($args[0]) . "*\n\n" .
                                       "Digite !menu para ver todos os comandos disponíveis."
                        ];
                    }
                } else {
                    $response = [
                        'success' => true,
                        'message' => "💡 *AJUDA: " . strtoupper($args[0]) . "*\n\n" .
                                   "Digite !menu para ver todos os comandos disponíveis."
                    ];
                }
            } else {
                // Se não tiver argumento, mostrar menu
                $loggedUser = getWhatsAppUser($pdo, $phoneNormalized);
                $userId = $loggedUser ? (int)$loggedUser['id'] : null;
                $response = [
                    'success' => true,
                    'message' => "💡 *AJUDA*\n\n" .
                               "Digite !ajuda COMANDO para ver detalhes de um comando específico.\n\n" .
                               "Exemplos:\n" .
                               "• !ajuda receita\n" .
                               "• !ajuda despesa\n" .
                               "• !ajuda saldo\n\n" .
                               "Ou digite !menu para ver todos os comandos."
                ];
            }
            break;

        case '!receita':
        case '/receita':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            if (count($args) < 2) {
                $response = [
                    'success' => false, 
                    'message' => '❌ *Formato incorreto!*\n\n' .
                               'Uso: !receita VALOR DESCRIÇÃO\n' .
                               'Exemplo: !receita 1500 Salário\n\n' .
                               '💡 Ou use: recebi 1500 Salário'
                ];
                break;
            }
            
            // Parse do valor com validação melhor
            if (function_exists('parseMoney')) {
                $value = parseMoney($args[0]);
            } else {
                $value = (float)str_replace(',', '.', $args[0]);
            }
            if (!$value || $value <= 0) {
                $response = ['success' => false, 'message' => '❌ Valor inválido! Use um número maior que zero.\n\nExemplo: !receita 1500 Salário'];
                break;
            }
            
            $description = implode(' ', array_slice($args, 1));
            if (empty(trim($description))) {
                $response = ['success' => false, 'message' => '❌ Descrição não pode estar vazia!\n\nExemplo: !receita 1500 Salário'];
                break;
            }
            $clientName = null;
            
            // Tentar encontrar cliente pelo nome
            $clientId = null;
            if (count($args) > 2) {
                $clientName = $args[count($args) - 1];
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE name LIKE ? AND (id_usuario = ? OR id_usuario IS NULL) LIMIT 1");
                $stmt->execute(["%$clientName%", $userId]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($client) {
                    $clientId = $client['id'];
                }
            }
            
            $result = registerTransaction($pdo, 'receita', $value, $description, null, $clientId, $userId, $phoneNormalized);
            
            if ($result['success']) {
                // Obter saldo do usuário específico
                $balance = getBalance($pdo, null, null, $userId);
                $saldoAtual = $balance['success'] ? formatMoney($balance['saldo']) : 'N/A';
                
                $response = [
                    'success' => true,
                    'message' => "💰 *Receita Registrada*\n\n" .
                               "Valor: " . formatMoney($value) . "\n" .
                               "Descrição: $description\n" .
                               ($clientName ? "Cliente: $clientName\n" : "") .
                               "ID: #" . $result['transaction_id'] . "\n" .
                               "Data: " . date('d/m/Y H:i') . "\n\n" .
                               "💵 Saldo atual: $saldoAtual\n" .
                               "✅ Registrado no painel!"
                ];
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $result['error']];
            }
            break;

        case '!despesa':
        case '/despesa':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            if (count($args) < 2) {
                $response = [
                    'success' => false, 
                    'message' => '❌ *Formato incorreto!*\n\n' .
                               'Uso: !despesa VALOR DESCRIÇÃO\n' .
                               'Exemplo: !despesa 50 Almoço\n\n' .
                               '💡 Ou use: gastei 50 Almoço'
                ];
                break;
            }
            
            // Parse do valor com validação melhor
            if (function_exists('parseMoney')) {
                $value = parseMoney($args[0]);
            } else {
                $value = (float)str_replace(',', '.', $args[0]);
            }
            if (!$value || $value <= 0) {
                $response = ['success' => false, 'message' => '❌ Valor inválido! Use um número maior que zero.\n\nExemplo: !despesa 50 Almoço'];
                break;
            }
            
            $description = implode(' ', array_slice($args, 1, -1));
            if (empty(trim($description))) {
                $description = $args[1] ?? 'Despesa';
            }
            $category = count($args) > 2 ? $args[count($args) - 1] : null;
            
            $result = registerTransaction($pdo, 'despesa', $value, $description, $category, null, $userId, $phoneNormalized);
            
            if ($result['success']) {
                $balance = getBalance($pdo, null, null, $userId);
                
                // Verificar se é um gasto alto e alertar
                $mensagemAlerta = "";
                if ($value >= 500) {
                    $mensagemAlerta = "\n⚠️ *Alerta: Gasto alto detectado!*\n";
                    if ($balance['success'] && $balance['receitas']['total'] > 0) {
                        $percent = ($value / $balance['receitas']['total']) * 100;
                        if ($percent > 10) {
                            $mensagemAlerta .= "Este gasto representa " . round($percent, 1) . "% da sua receita do mês!\n";
                        }
                    }
                }
                
                // Obter saldo atualizado após despesa
                $balance = getBalance($pdo, null, null, $userId);
                $saldoAtual = $balance['success'] ? formatMoney($balance['saldo']) : 'N/A';
                
                $response = [
                    'success' => true,
                    'message' => "💸 *Despesa Registrada*\n\n" .
                               "Valor: " . formatMoney($value) . "\n" .
                               "Descrição: $description\n" .
                               ($category ? "Categoria: $category\n" : "") .
                               "ID: #" . $result['transaction_id'] . "\n" .
                               "Data: " . date('d/m/Y H:i') . "\n" .
                               $mensagemAlerta .
                               "\n💵 Saldo atual: $saldoAtual\n" .
                               "✅ Registrado no painel!"
                ];
                
                // Enviar alerta assíncrono se for gasto alto
                if ($value >= 500) {
                    // Executar script de alerta em background (não bloqueia resposta)
                    if (function_exists('exec')) {
                        $scriptPath = __DIR__ . '/enviar_alertas_gastos.php';
                        if (file_exists($scriptPath)) {
                            // Executar em background (não esperar resposta)
                            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                                pclose(popen("start /B php \"$scriptPath\"", "r"));
                            } else {
                                exec("php \"$scriptPath\" > /dev/null 2>&1 &");
                            }
                        }
                    }
                }
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $result['error']];
            }
            break;

        case '!saldo':
        case '/saldo':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $month = isset($args[0]) ? (int)$args[0] : null;
            $year = isset($args[1]) ? (int)$args[1] : (int)date('Y');
            if (!$month) $month = (int)date('m');
            
            // Debug: verificar transações do usuário
            error_log("!saldo: userId=$userId, month=$month, year=$year");
            
            // Verificar quantas transações o usuário tem
            try {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as total, 
                                           SUM(CASE WHEN type = 'receita' THEN value ELSE 0 END) as receitas,
                                           SUM(CASE WHEN type = 'despesa' THEN value ELSE 0 END) as despesas
                                           FROM transactions 
                                           WHERE id_usuario = ? AND YEAR(created_at) = ? AND MONTH(created_at) = ?");
                $checkStmt->execute([$userId, $year, $month]);
                $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
                error_log("!saldo: Transações encontradas - Total: {$checkResult['total']}, Receitas: {$checkResult['receitas']}, Despesas: {$checkResult['despesas']}");
            } catch (Exception $e) {
                error_log("!saldo: Erro ao verificar transações: " . $e->getMessage());
            }
            
            $balance = getBalance($pdo, $month, $year, $userId);
            
            if ($balance['success']) {
                $monthName = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                             'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                
                // Debug: log do resultado
                error_log("!saldo: Resultado - Receitas: {$balance['receitas']['total']}, Despesas: {$balance['despesas']['total']}, Saldo: {$balance['saldo']}");
                
                $response = [
                    'success' => true,
                    'message' => "💰 *SALDO - " . strtoupper($monthName[$month]) . "/$year*\n\n" .
                               "📈 Receitas: " . formatMoney($balance['receitas']['total']) . 
                               " (" . $balance['receitas']['count'] . " transações)\n" .
                               "📉 Despesas: " . formatMoney($balance['despesas']['total']) . 
                               " (" . $balance['despesas']['count'] . " transações)\n" .
                               "━━━━━━━━━━━━━━━━━━━━━\n" .
                               "💵 Saldo: " . formatMoney($balance['saldo']) . "\n\n" .
                               "📊 _Use !relatorio para detalhes_"
                ];
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $balance['error']];
            }
            break;

        case '!extrato':
        case '/extrato':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $startDate = isset($args[0]) ? $args[0] : date('Y-m-01');
            $endDate = isset($args[1]) ? $args[1] : date('Y-m-t');
            
            $extract = getExtract($pdo, $startDate, $endDate, $userId, 20);
            
            if ($extract['success']) {
                $msg = "📊 *EXTRATO*\n\n";
                $msg .= "Período: " . formatDate($startDate) . " a " . formatDate($endDate) . "\n\n";
                
                foreach ($extract['transactions'] as $t) {
                    $icon = $t['type'] === 'receita' ? '💰' : '💸';
                    $msg .= "$icon " . formatMoney($t['value']) . " - " . $t['description'] . "\n";
                    $msg .= "   ID: #" . $t['id'] . " | " . formatDate($t['created_at'], 'd/m/Y H:i') . "\n\n";
                }
                
                $msg .= "Total: " . $extract['count'] . " transações";
                
                $response = ['success' => true, 'message' => $msg];
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $extract['error']];
            }
            break;

        case '!clientes':
        case '/clientes':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $stmt = $pdo->prepare("SELECT id, name, phone, whatsapp_number FROM clients WHERE id_usuario = ? OR id_usuario IS NULL ORDER BY name LIMIT 50");
            $stmt->execute([$userId]);
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $msg = "👥 *CLIENTES*\n\n";
            foreach ($clients as $c) {
                $msg .= "ID: #" . $c['id'] . " - " . $c['name'] . "\n";
                if ($c['phone']) $msg .= "   📞 " . $c['phone'] . "\n";
                $msg .= "\n";
            }
            $msg .= "Total: " . count($clients) . " clientes";
            
            $response = ['success' => true, 'message' => $msg];
            break;

        case '!pendencias':
        case '/pendencias':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $clientId = isset($args[0]) ? (int)$args[0] : null;
            $pendencies = getClientPendencies($pdo, $clientId, $userId);
            
            if ($pendencies['success']) {
                if (empty($pendencies['pendencies'])) {
                    $response = ['success' => true, 'message' => '✅ Nenhuma pendência encontrada!'];
                } else {
                    $msg = "⚠️ *PENDÊNCIAS*\n\n";
                    foreach ($pendencies['pendencies'] as $p) {
                        $msg .= "ID: #" . $p['id'] . "\n";
                        $msg .= "💰 Valor: " . formatMoney($p['value']) . "\n";
                        $msg .= "📅 Vencimento: " . formatDate($p['due_date']) . "\n";
                        $msg .= "📝 " . ($p['description'] ?? 'Sem descrição') . "\n";
                        if ($p['client_name']) $msg .= "👤 Cliente: " . $p['client_name'] . "\n";
                        $msg .= "\n";
                    }
                    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "💵 Total pendente: " . formatMoney($pendencies['total']) . "\n\n";
                    $msg .= "💡 _Use /lembrar ID para notificar_";
                    
                    $response = ['success' => true, 'message' => $msg];
                }
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $pendencies['error']];
            }
            break;

        case '!relatorio':
        case '/relatorio':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $month = isset($args[0]) ? (int)$args[0] : null;
            $year = isset($args[1]) ? (int)$args[1] : (int)date('Y');
            if (!$month) $month = (int)date('m');
            
            $report = generateMonthReport($pdo, $month, $year, $userId);
            
            if ($report['success']) {
                $monthName = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                             'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                
                $msg = "📊 *RELATÓRIO - " . strtoupper($monthName[$month]) . "/$year*\n\n";
                
                $msg .= "*RECEITAS*\n";
                $msg .= "💰 Total: " . formatMoney($report['balance']['receitas']['total']) . "\n";
                $msg .= "📦 Transações: " . $report['balance']['receitas']['count'] . "\n\n";
                
                $msg .= "*DESPESAS*\n";
                $msg .= "💸 Total: " . formatMoney($report['balance']['despesas']['total']) . "\n";
                $msg .= "📦 Transações: " . $report['balance']['despesas']['count'] . "\n";
                $msg .= "🏷️ Categorias: " . count($report['top_categories']) . "\n\n";
                
                if (!empty($report['top_clients'])) {
                    $msg .= "*TOP 5 CLIENTES*\n";
                    $i = 1;
                    foreach ($report['top_clients'] as $client) {
                        $msg .= "$i. " . $client['name'] . " - " . formatMoney($client['total']) . "\n";
                        $i++;
                    }
                    $msg .= "\n";
                }
                
                $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "💵 *SALDO FINAL: " . formatMoney($report['balance']['saldo']) . "*";
                
                $response = ['success' => true, 'message' => $msg];
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $report['error']];
            }
            break;

        case '!dashboard':
        case '/dashboard':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            try {
                $balance = getBalance($pdo, null, null, $userId);
                if (!$balance['success']) {
                    $response = ['success' => false, 'message' => '❌ Erro ao calcular saldo: ' . ($balance['error'] ?? 'Erro desconhecido')];
                    break;
                }
                
                // Tentar buscar pendências, mas não falhar se a tabela não existir
                $pendencies = ['success' => true, 'count' => 0, 'total' => 0];
                try {
                    $pendenciesResult = getClientPendencies($pdo, null, $userId);
                    if ($pendenciesResult['success']) {
                        $pendencies = $pendenciesResult;
                    }
                } catch (Exception $e) {
                    error_log("Erro ao buscar pendências no dashboard: " . $e->getMessage());
                    // Continuar sem pendências se houver erro
                }
                
                $msg = "📊 *DASHBOARD GERAL*\n\n";
                $msg .= "💰 Receitas: " . formatMoney($balance['receitas']['total']) . "\n";
                $msg .= "💸 Despesas: " . formatMoney($balance['despesas']['total']) . "\n";
                $msg .= "💵 Saldo: " . formatMoney($balance['saldo']) . "\n\n";
                $msg .= "⚠️ Pendências: " . $pendencies['count'] . " (" . formatMoney($pendencies['total']) . ")";
                
                $response = ['success' => true, 'message' => $msg];
            } catch (Exception $e) {
                error_log("Erro no comando dashboard: " . $e->getMessage());
                $response = ['success' => false, 'message' => '❌ Erro ao gerar dashboard: ' . $e->getMessage()];
            }
            break;

        // ============================================
        // COMANDOS DE TAREFAS
        // ============================================
        case '!tarefas':
        case '!tarefa':
        case '/tarefas':
        case '/tarefa':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $tasks = getTasks($pdo, $userId, 'pendente', 10);
            
            if (!$tasks['success']) {
                $response = ['success' => false, 'message' => '❌ ' . $tasks['error']];
                break;
            }
            
            if ($tasks['count'] === 0) {
                $response = [
                    'success' => true,
                    'message' => "✅ *Nenhuma tarefa pendente!*\n\nVocê está em dia! 🎉"
                ];
                break;
            }
            
            $msg = "📋 *SUAS TAREFAS PENDENTES*\n\n";
            foreach ($tasks['tasks'] as $task) {
                $msg .= "ID: #" . $task['id'] . "\n";
                $msg .= formatPriority($task['prioridade']) . "\n";
                $msg .= "📝 " . $task['descricao'] . "\n";
                $msg .= "📅 " . formatTaskDate($task['data_limite']) . "\n\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "Total: " . $tasks['count'] . " tarefa(s)\n\n";
            $msg .= "💡 Use !concluir ID para concluir uma tarefa";
            
            $response = ['success' => true, 'message' => $msg];
            break;

        case '!addtarefa':
        case '!adicionar':
        case '!novatarefa':
        case '/addtarefa':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            if (count($args) < 1) {
                $response = ['success' => false, 'message' => '❌ Uso: !addtarefa DESCRIÇÃO [PRIORIDADE] [DATA]\n\nExemplo: !addtarefa Estudar PHP Alta 2025-01-20'];
                break;
            }
            
            $description = implode(' ', array_slice($args, 0, -2));
            $priority = 'Média';
            $dueDate = null;
            
            // Tentar identificar prioridade e data nos últimos argumentos
            $lastArgs = array_slice($args, -2);
            $priorities = ['Alta', 'Média', 'Baixa'];
            
            foreach ($lastArgs as $arg) {
                if (in_array(ucfirst(strtolower($arg)), $priorities)) {
                    $priority = ucfirst(strtolower($arg));
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $arg)) {
                    // Formato YYYY-MM-DD ou DD/MM/YYYY
                    if (strpos($arg, '/') !== false) {
                        $parts = explode('/', $arg);
                        $dueDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                    } else {
                        $dueDate = $arg;
                    }
                }
            }
            
            // Se a descrição ficou vazia, usar todos os args exceto prioridade e data
            if (empty($description)) {
                $description = implode(' ', array_filter($args, function($arg) use ($priority, $dueDate) {
                    return strtolower($arg) !== strtolower($priority) && $arg !== $dueDate;
                }));
            }
            
            if (empty($description)) {
                $response = ['success' => false, 'message' => '❌ Descrição da tarefa não pode estar vazia'];
                break;
            }
            
            $result = addTask($pdo, $userId, $description, $priority, $dueDate);
            
            if ($result['success']) {
                $msg = "✅ *Tarefa Criada!*\n\n";
                $msg .= "📝 " . $description . "\n";
                $msg .= formatPriority($priority) . "\n";
                if ($dueDate) {
                    $msg .= "📅 " . formatTaskDate($dueDate) . "\n";
                }
                $msg .= "ID: #" . $result['task_id'] . "\n\n";
                $msg .= "Use !tarefas para ver todas as tarefas";
                
                $response = ['success' => true, 'message' => $msg];
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $result['error']];
            }
            break;

        case '!concluir':
        case '!feito':
        case '/concluir':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            if (count($args) < 1) {
                $response = ['success' => false, 'message' => '❌ Uso: !concluir ID\n\nExemplo: !concluir 5'];
                break;
            }
            
            $taskId = (int)$args[0];
            $result = completeTask($pdo, $taskId, $userId);
            
            if ($result['success']) {
                $response = [
                    'success' => true,
                    'message' => "✅ *Tarefa #$taskId concluída!*\n\nParabéns! 🎉\n\nUse !tarefas para ver suas tarefas pendentes"
                ];
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $result['error']];
            }
            break;

        case '!urgentes':
        case '!prioritarias':
        case '/urgentes':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $tasks = getUrgentTasks($pdo, $userId, 10);
            
            if (!$tasks['success']) {
                $response = ['success' => false, 'message' => '❌ ' . $tasks['error']];
                break;
            }
            
            if ($tasks['count'] === 0) {
                $response = [
                    'success' => true,
                    'message' => "✅ *Nenhuma tarefa urgente!*\n\nVocê está em dia! 🎉"
                ];
                break;
            }
            
            $msg = "🚨 *TAREFAS URGENTES*\n\n";
            foreach ($tasks['tasks'] as $task) {
                $msg .= "ID: #" . $task['id'] . "\n";
                $msg .= formatPriority($task['prioridade']) . "\n";
                $msg .= "📝 " . $task['descricao'] . "\n";
                $msg .= "📅 " . formatTaskDate($task['data_limite']) . "\n";
                $msg .= "⚠️ " . $task['status_urgencia'] . "\n\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "Total: " . $tasks['count'] . " tarefa(s) urgente(s)";
            
            $response = ['success' => true, 'message' => $msg];
            break;

        case '!tarefahoje':
        case '!hoje':
        case '/tarefahoje':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $tasks = getTodayTasks($pdo, $userId);
            
            if (!$tasks['success']) {
                $response = ['success' => false, 'message' => '❌ ' . $tasks['error']];
                break;
            }
            
            if ($tasks['count'] === 0) {
                $response = [
                    'success' => true,
                    'message' => "✅ *Nenhuma tarefa para hoje!*\n\nAproveite o dia! 😊"
                ];
                break;
            }
            
            $msg = "📅 *TAREFAS DE HOJE*\n\n";
            foreach ($tasks['tasks'] as $task) {
                $msg .= "ID: #" . $task['id'] . "\n";
                $msg .= formatPriority($task['prioridade']) . "\n";
                $msg .= "📝 " . $task['descricao'] . "\n";
                if ($task['data_limite']) {
                    $msg .= "📅 " . formatTaskDate($task['data_limite']) . "\n";
                }
                $msg .= "\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "Total: " . $tasks['count'] . " tarefa(s)";
            
            $response = ['success' => true, 'message' => $msg];
            break;

        case '!deletartarefa':
        case '!remover':
        case '/deletartarefa':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            if (count($args) < 1) {
                $response = ['success' => false, 'message' => '❌ Uso: !deletartarefa ID\n\nExemplo: !deletartarefa 5'];
                break;
            }
            
            $taskId = (int)$args[0];
            $result = deleteTask($pdo, $taskId, $userId);
            
            if ($result['success']) {
                $response = [
                    'success' => true,
                    'message' => "✅ *Tarefa #$taskId deletada!*\n\nUse !tarefas para ver suas tarefas"
                ];
            } else {
                $response = ['success' => false, 'message' => '❌ ' . $result['error']];
            }
            break;

        case '!estatisticas':
        case '!stats':
        case '/estatisticas':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $stats = getTaskStats($pdo, $userId);
            
            if (!$stats['success']) {
                $response = ['success' => false, 'message' => '❌ ' . $stats['error']];
                break;
            }
            
            $msg = "📊 *ESTATÍSTICAS DE TAREFAS*\n\n";
            $msg .= "📋 Total: " . $stats['total'] . "\n";
            $msg .= "✅ Concluídas: " . $stats['concluidas'] . "\n";
            $msg .= "⏳ Pendentes: " . $stats['pendentes'] . "\n";
            $msg .= "🔴 Alta Prioridade: " . $stats['alta_prioridade'] . "\n";
            
            if ($stats['vencidas'] > 0) {
                $msg .= "⚠️ Vencidas: " . $stats['vencidas'] . "\n";
            }
            
            if ($stats['total'] > 0) {
                $percent = round(($stats['concluidas'] / $stats['total']) * 100);
                $msg .= "\n━━━━━━━━━━━━━━━━━━━━━\n";
                $msg .= "📈 Progresso: $percent%";
            }
            
            $response = ['success' => true, 'message' => $msg];
            break;

        case '!semana':
        case '!resumosemanal':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            // Calcular início e fim da semana (segunda a domingo)
            $today = new DateTime();
            $dayOfWeek = (int)$today->format('w'); // 0 = domingo, 1 = segunda
            $monday = clone $today;
            $monday->modify('-' . (($dayOfWeek == 0 ? 7 : $dayOfWeek) - 1) . ' days');
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            
            $startDate = $monday->format('Y-m-d');
            $endDate = $sunday->format('Y-m-d');
            
            // Receitas da semana
            $sqlReceitas = "SELECT COALESCE(SUM(value), 0) as total, COUNT(*) as count 
                           FROM transactions 
                           WHERE type = 'receita' 
                           AND id_usuario = ?
                           AND DATE(created_at) BETWEEN ? AND ?";
            $stmt = $pdo->prepare($sqlReceitas);
            $stmt->execute([$userId, $startDate, $endDate]);
            $receitas = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Despesas da semana
            $sqlDespesas = "SELECT COALESCE(SUM(value), 0) as total, COUNT(*) as count 
                           FROM transactions 
                           WHERE type = 'despesa' 
                           AND id_usuario = ?
                           AND DATE(created_at) BETWEEN ? AND ?";
            $stmt = $pdo->prepare($sqlDespesas);
            $stmt->execute([$userId, $startDate, $endDate]);
            $despesas = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Tarefas concluídas da semana
            $tasksWeek = getTasks($pdo, $userId, 'concluida', 100);
            $tasksConcluidas = 0;
            if ($tasksWeek['success']) {
                foreach ($tasksWeek['tasks'] as $task) {
                    $taskDate = new DateTime($task['data_criacao']);
                    if ($taskDate >= $monday && $taskDate <= $sunday) {
                        $tasksConcluidas++;
                    }
                }
            }
            
            $saldoSemana = $receitas['total'] - $despesas['total'];
            
            $msg = "📊 *RESUMO SEMANAL*\n\n";
            $msg .= "📅 Período: " . $monday->format('d/m') . " a " . $sunday->format('d/m/Y') . "\n\n";
            $msg .= "💰 *Receitas*\n";
            $msg .= "Total: " . formatMoney($receitas['total']) . "\n";
            $msg .= "Transações: " . $receitas['count'] . "\n\n";
            $msg .= "💸 *Despesas*\n";
            $msg .= "Total: " . formatMoney($despesas['total']) . "\n";
            $msg .= "Transações: " . $despesas['count'] . "\n\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "💵 Saldo da Semana: " . formatMoney($saldoSemana) . "\n\n";
            $msg .= "✅ Tarefas Concluídas: $tasksConcluidas";
            
            $response = ['success' => true, 'message' => $msg];
            break;

        case '!comparar':
        case '!comparacao':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            $currentMonth = (int)date('m');
            $currentYear = (int)date('Y');
            $lastMonth = $currentMonth - 1;
            $lastYear = $currentYear;
            if ($lastMonth < 1) {
                $lastMonth = 12;
                $lastYear--;
            }
            
            $balanceCurrent = getBalance($pdo, $currentMonth, $currentYear, $userId);
            $balanceLast = getBalance($pdo, $lastMonth, $lastYear, $userId);
            
            if (!$balanceCurrent['success'] || !$balanceLast['success']) {
                $response = ['success' => false, 'message' => '❌ Erro ao calcular comparação'];
                break;
            }
            
            $monthNames = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                          'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            
            $diffReceitas = $balanceCurrent['receitas']['total'] - $balanceLast['receitas']['total'];
            $diffDespesas = $balanceCurrent['despesas']['total'] - $balanceLast['despesas']['total'];
            $diffSaldo = $balanceCurrent['saldo'] - $balanceLast['saldo'];
            
            $msg = "📊 *COMPARAÇÃO DE MESES*\n\n";
            $msg .= "📅 " . strtoupper($monthNames[$currentMonth]) . "/$currentYear vs " . strtoupper($monthNames[$lastMonth]) . "/$lastYear\n\n";
            
            $msg .= "💰 *Receitas*\n";
            $msg .= "Este mês: " . formatMoney($balanceCurrent['receitas']['total']) . "\n";
            $msg .= "Mês anterior: " . formatMoney($balanceLast['receitas']['total']) . "\n";
            $emoji = $diffReceitas >= 0 ? '📈' : '📉';
            $msg .= "$emoji Diferença: " . formatMoney(abs($diffReceitas)) . "\n\n";
            
            $msg .= "💸 *Despesas*\n";
            $msg .= "Este mês: " . formatMoney($balanceCurrent['despesas']['total']) . "\n";
            $msg .= "Mês anterior: " . formatMoney($balanceLast['despesas']['total']) . "\n";
            $emoji = $diffDespesas <= 0 ? '📉' : '📈';
            $msg .= "$emoji Diferença: " . formatMoney(abs($diffDespesas)) . "\n\n";
            
            $msg .= "💵 *Saldo*\n";
            $msg .= "Este mês: " . formatMoney($balanceCurrent['saldo']) . "\n";
            $msg .= "Mês anterior: " . formatMoney($balanceLast['saldo']) . "\n";
            $emoji = $diffSaldo >= 0 ? '📈' : '📉';
            $msg .= "$emoji Diferença: " . formatMoney(abs($diffSaldo));
            
            $response = ['success' => true, 'message' => $msg];
            break;

        default:
            // Tentar sugerir comando similar
            $suggestionMsg = '';
            if (function_exists('suggestCommand')) {
                try {
                    $availableCommands = [
                        '!receita' => ['receita', 'recebi', 'ganhei'],
                        '!despesa' => ['despesa', 'gastei', 'paguei'],
                        '!saldo' => ['saldo', 'quanto tenho'],
                        '!tarefas' => ['tarefas', 'tarefa'],
                        '!menu' => ['menu', 'ajuda', 'help']
                    ];
                    
                    $suggestion = suggestCommand($command, $availableCommands);
                    if ($suggestion) {
                        $suggestionMsg = "\n\n💡 Você quis dizer: $suggestion?";
                    }
                } catch (Exception $e) {
                    error_log("Erro ao sugerir comando: " . $e->getMessage());
                }
            }
            
            $response = [
                'success' => false, 
                'message' => "❌ Comando não reconhecido: $command\n\n" .
                           "Digite !menu para ver todos os comandos." .
                           $suggestionMsg
            ];
    }
} catch (Exception $e) {
    error_log("Erro em admin_bot_api.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $response = [
        'success' => false, 
        'message' => '❌ Erro ao processar comando: ' . $e->getMessage(),
        'error_details' => $e->getFile() . ':' . $e->getLine()
    ];
} catch (Error $e) {
    error_log("Erro fatal em admin_bot_api.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $response = [
        'success' => false, 
        'message' => '❌ Erro fatal: ' . $e->getMessage(),
        'error_details' => $e->getFile() . ':' . $e->getLine()
    ];
}

// Salvar log
try {
    writeLog($pdo, $phoneNormalized, $command, $message, $response['message'] ?? 'Erro desconhecido', $response['success'] ?? false);
} catch (Exception $e) {
    error_log("Erro ao salvar log: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

