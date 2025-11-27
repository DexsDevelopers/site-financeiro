<?php
// admin_bot_api.php - API para processar comandos do bot WhatsApp
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'includes/db_connect.php';
require_once 'includes/finance_helper.php';

// Carregar configuração
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
if (!$config) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar configuração']);
    exit;
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
foreach ($config['ADMIN_WHATSAPP_NUMBERS'] as $adminNum) {
    if (normalizePhone($adminNum) === $phoneNormalized) {
        $isAdmin = true;
        break;
    }
}

// Função para obter usuário logado via WhatsApp
function getWhatsAppUser(PDO $pdo, string $phone): ?array {
    try {
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
            $updateStmt = $pdo->prepare("UPDATE whatsapp_sessions SET last_activity = NOW() WHERE phone_number = ? AND is_active = 1");
            $updateStmt->execute([$phone]);
        } else {
            error_log("getWhatsAppUser: Nenhuma sessão ativa encontrada para telefone: " . $phone);
            // Debug: verificar se existe sessão inativa
            $checkStmt = $pdo->prepare("SELECT phone_number, is_active, user_id FROM whatsapp_sessions WHERE phone_number = ?");
            $checkStmt->execute([$phone]);
            $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($checkResult) {
                error_log("getWhatsAppUser: Sessão encontrada mas inativa. is_active: " . $checkResult['is_active']);
            } else {
                error_log("getWhatsAppUser: Nenhuma sessão encontrada (nem ativa nem inativa) para telefone: " . $phone);
            }
        }
        
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Erro ao buscar usuário WhatsApp: " . $e->getMessage());
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
$loggedUser = getWhatsAppUser($pdo, $phoneNormalized);
$userId = $loggedUser ? (int)$loggedUser['id'] : null;

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
            break;

        case '!logout':
            $response = logoutWhatsApp($pdo, $phoneNormalized);
            break;

        case '!status':
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
        case '/menu':
        case '/help':
            $response = [
                'success' => true,
                'message' => "📋 *MENU DE COMANDOS*\n\n" .
                           ($loggedUser ? "✅ Logado como: " . $loggedUser['nome'] . "\n\n" : "⚠️ *Você não está logado!*\nUse: !login EMAIL SENHA\n\n") .
                           "*AUTENTICAÇÃO*\n" .
                           "🔐 !login EMAIL SENHA\n" .
                           "🚪 !logout\n" .
                           "ℹ️ !status\n\n" .
                           "*FINANCEIRO*\n" .
                           "💰 !receita VALOR DESCRIÇÃO [CLIENTE]\n" .
                           "💸 !despesa VALOR DESCRIÇÃO [CATEGORIA]\n" .
                           "💵 !saldo [MÊS]\n" .
                           "📊 !extrato [INÍCIO] [FIM]\n" .
                           "🗑️ !deletar ID\n\n" .
                           "*CLIENTES*\n" .
                           "👤 !cliente NOME TELEFONE [EMAIL]\n" .
                           "📋 !clientes\n" .
                           "ℹ️ !clienteinfo ID\n" .
                           "⚠️ !pendencias [ID]\n\n" .
                           "*COMPROVANTES*\n" .
                           "📸 !comprovante ID\n" .
                           "👁️ !vercomprovante ID\n\n" .
                           "*RELATÓRIOS*\n" .
                           "📈 !relatorio [MÊS]\n" .
                           "📊 !dashboard\n" .
                           "🏆 !topo [LIMITE]\n\n" .
                           "*COBRANÇAS*\n" .
                           "💳 !cobrar CLIENTE_ID VALOR VENCIMENTO DESCRIÇÃO\n" .
                           "🔔 !lembrar COBRANCA_ID\n" .
                           "📨 !notificar CLIENTE_ID MENSAGEM\n" .
                           "✅ !pagar COBRANCA_ID\n\n" .
                           "💡 Digite !ajuda COMANDO para detalhes"
            ];
            break;

        case '!receita':
        case '/receita':
            if (!$userId) {
                $response = ['success' => false, 'message' => '⚠️ Você precisa estar logado! Use: !login EMAIL SENHA'];
                break;
            }
            
            if (count($args) < 2) {
                $response = ['success' => false, 'message' => '❌ Uso: !receita VALOR DESCRIÇÃO [CLIENTE]'];
                break;
            }
            
            $value = (float)str_replace(',', '.', $args[0]);
            $description = implode(' ', array_slice($args, 1));
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
                $balance = getBalance($pdo);
                $response = [
                    'success' => true,
                    'message' => "💰 *Receita Registrada*\n\n" .
                               "Valor: " . formatMoney($value) . "\n" .
                               "Descrição: $description\n" .
                               ($clientName ? "Cliente: $clientName\n" : "") .
                               "ID: #" . $result['transaction_id'] . "\n" .
                               "Data: " . date('d/m/Y H:i') . "\n\n" .
                               "✅ Saldo atualizado!"
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
                $response = ['success' => false, 'message' => '❌ Uso: !despesa VALOR DESCRIÇÃO [CATEGORIA]'];
                break;
            }
            
            $value = (float)str_replace(',', '.', $args[0]);
            $description = implode(' ', array_slice($args, 1, -1));
            $category = count($args) > 2 ? $args[count($args) - 1] : null;
            
            $result = registerTransaction($pdo, 'despesa', $value, $description, $category, null, $userId, $phoneNormalized);
            
            if ($result['success']) {
                $balance = getBalance($pdo);
                $response = [
                    'success' => true,
                    'message' => "💸 *Despesa Registrada*\n\n" .
                               "Valor: " . formatMoney($value) . "\n" .
                               "Descrição: $description\n" .
                               ($category ? "Categoria: $category\n" : "") .
                               "ID: #" . $result['transaction_id'] . "\n" .
                               "Data: " . date('d/m/Y H:i') . "\n\n" .
                               "✅ Registrado com sucesso!"
                ];
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
            
            $balance = getBalance($pdo, $month, $year, $userId);
            
            if ($balance['success']) {
                $monthName = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                             'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                
                $response = [
                    'success' => true,
                    'message' => "💰 *SALDO - " . strtoupper($monthName[$month]) . "/$year*\n\n" .
                               "📈 Receitas: " . formatMoney($balance['receitas']['total']) . 
                               " (" . $balance['receitas']['count'] . " transações)\n" .
                               "📉 Despesas: " . formatMoney($balance['despesas']['total']) . 
                               " (" . $balance['despesas']['count'] . " transações)\n" .
                               "━━━━━━━━━━━━━━━━━━━━━\n" .
                               "💵 Saldo: " . formatMoney($balance['saldo']) . "\n\n" .
                               "📊 _Use /relatorio para detalhes_"
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
            
            $balance = getBalance($pdo, null, null, $userId);
            $pendencies = getClientPendencies($pdo, null, $userId);
            
            $msg = "📊 *DASHBOARD GERAL*\n\n";
            $msg .= "💰 Receitas: " . formatMoney($balance['receitas']['total']) . "\n";
            $msg .= "💸 Despesas: " . formatMoney($balance['despesas']['total']) . "\n";
            $msg .= "💵 Saldo: " . formatMoney($balance['saldo']) . "\n\n";
            $msg .= "⚠️ Pendências: " . $pendencies['count'] . " (" . formatMoney($pendencies['total']) . ")";
            
            $response = ['success' => true, 'message' => $msg];
            break;

        default:
            $response = ['success' => false, 'message' => '❌ Comando não reconhecido. Digite !menu para ver os comandos disponíveis.'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => '❌ Erro: ' . $e->getMessage()];
}

// Salvar log
writeLog($pdo, $phoneNormalized, $command, $message, $response['message'], $response['success']);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

