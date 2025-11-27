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

if ($token !== $config['WHATSAPP_API_TOKEN']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
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

// Verificar se é admin
$isAdmin = false;
foreach ($config['ADMIN_WHATSAPP_NUMBERS'] as $adminNum) {
    if (normalizePhone($adminNum) === $phoneNormalized) {
        $isAdmin = true;
        break;
    }
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
        case '!menu':
        case '!help':
        case '/menu':
        case '/help':
            $response = [
                'success' => true,
                'message' => "📋 *MENU DE COMANDOS*\n\n" .
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
                $stmt = $pdo->prepare("SELECT id FROM clients WHERE name LIKE ? LIMIT 1");
                $stmt->execute(["%$clientName%"]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($client) {
                    $clientId = $client['id'];
                }
            }
            
            $result = registerTransaction($pdo, 'receita', $value, $description, null, $clientId, null, $phoneNormalized);
            
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
            if (count($args) < 2) {
                $response = ['success' => false, 'message' => '❌ Uso: !despesa VALOR DESCRIÇÃO [CATEGORIA]'];
                break;
            }
            
            $value = (float)str_replace(',', '.', $args[0]);
            $description = implode(' ', array_slice($args, 1, -1));
            $category = count($args) > 2 ? $args[count($args) - 1] : null;
            
            $result = registerTransaction($pdo, 'despesa', $value, $description, $category, null, null, $phoneNormalized);
            
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
            $month = isset($args[0]) ? (int)$args[0] : null;
            $year = isset($args[1]) ? (int)$args[1] : (int)date('Y');
            if (!$month) $month = (int)date('m');
            
            $balance = getBalance($pdo, $month, $year);
            
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
            $startDate = isset($args[0]) ? $args[0] : date('Y-m-01');
            $endDate = isset($args[1]) ? $args[1] : date('Y-m-t');
            
            $extract = getExtract($pdo, $startDate, $endDate, null, 20);
            
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
            $stmt = $pdo->query("SELECT id, name, phone, whatsapp_number FROM clients ORDER BY name LIMIT 50");
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
            $clientId = isset($args[0]) ? (int)$args[0] : null;
            $pendencies = getClientPendencies($pdo, $clientId);
            
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
            $month = isset($args[0]) ? (int)$args[0] : null;
            $year = isset($args[1]) ? (int)$args[1] : (int)date('Y');
            if (!$month) $month = (int)date('m');
            
            $report = generateMonthReport($pdo, $month, $year);
            
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
            $balance = getBalance($pdo);
            $pendencies = getClientPendencies($pdo);
            
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

