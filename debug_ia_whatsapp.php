<?php
// debug_ia_whatsapp.php - Página de diagnóstico da IA do WhatsApp Bot

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';

$userId = $_SESSION['user_id'] ?? 1; // Fallback para teste

$results = [];
$hasErrors = false;

function addResult($category, $test, $status, $message = '', $details = '') {
    global $results;
    $results[] = [
        'category' => $category,
        'test' => $test,
        'status' => $status, // 'success', 'warning', 'error'
        'message' => $message,
        'details' => $details
    ];
}

// 1. Verificar arquivos necessários
addResult('Arquivos', 'Verificar arquivos', 'success', 'Verificando arquivos necessários...', '');
$requiredFiles = [
    'admin_bot_ia.php' => 'Endpoint de IA para WhatsApp',
    'admin_bot_api.php' => 'API principal do bot',
    'includes/db_connect.php' => 'Conexão com banco',
    'includes/finance_helper.php' => 'Funções financeiras',
    'includes/tasks_helper.php' => 'Funções de tarefas',
    'includes/rate_limiter.php' => 'Rate limiter',
    'config.json' => 'Configurações'
];

foreach ($requiredFiles as $file => $desc) {
    if (file_exists($file)) {
        addResult('Arquivos', $file, 'success', "✅ $desc existe", '');
    } else {
        addResult('Arquivos', $file, 'error', "❌ $desc não encontrado", '');
        $hasErrors = true;
    }
}

// 2. Verificar GEMINI_API_KEY
if (defined('GEMINI_API_KEY')) {
    if (!empty(GEMINI_API_KEY)) {
        $keyPreview = substr(GEMINI_API_KEY, 0, 10) . '...' . substr(GEMINI_API_KEY, -5);
        addResult('Configuração', 'GEMINI_API_KEY', 'success', "✅ API Key configurada", "Preview: $keyPreview");
    } else {
        addResult('Configuração', 'GEMINI_API_KEY', 'error', "❌ API Key vazia", '');
        $hasErrors = true;
    }
} else {
    addResult('Configuração', 'GEMINI_API_KEY', 'error', "❌ API Key não definida", '');
    $hasErrors = true;
}

// 3. Verificar conexão com banco
try {
    if (isset($pdo) && $pdo !== null) {
        $pdo->query("SELECT 1");
        addResult('Banco de Dados', 'Conexão', 'success', "✅ Conexão com banco OK", '');
    } else {
        addResult('Banco de Dados', 'Conexão', 'error', "❌ Conexão com banco falhou", '');
        $hasErrors = true;
    }
} catch (Exception $e) {
    addResult('Banco de Dados', 'Conexão', 'error', "❌ Erro: " . $e->getMessage(), '');
    $hasErrors = true;
}

// 4. Verificar tabelas necessárias
$requiredTables = ['transacoes', 'categorias', 'contas', 'tarefas', 'rate_limit_ia'];
foreach ($requiredTables as $table) {
    try {
        if (isset($pdo) && $pdo !== null) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                addResult('Banco de Dados', "Tabela: $table", 'success', "✅ Tabela existe", '');
            } else {
                addResult('Banco de Dados', "Tabela: $table", 'warning', "⚠️ Tabela não encontrada", '');
            }
        }
    } catch (Exception $e) {
        addResult('Banco de Dados', "Tabela: $table", 'error', "❌ Erro: " . $e->getMessage(), '');
    }
}

// 5. Verificar Rate Limiter
try {
    if (isset($pdo) && $pdo !== null) {
        $rateLimiter = new RateLimiter($pdo);
        $stats = $rateLimiter->getUsageStats($userId, 'gemini');
        addResult('Rate Limiter', 'Status', 'success', "✅ Rate limiter funcionando", json_encode($stats, JSON_PRETTY_PRINT));
    }
} catch (Exception $e) {
    addResult('Rate Limiter', 'Status', 'warning', "⚠️ Erro: " . $e->getMessage(), '');
}

// 6. Verificar funções de IA
$requiredFunctions = [
    'getResumoFinanceiro',
    'getPrincipaisCategoriasGasto',
    'getTarefasDoUsuario',
    'getTarefasUrgentes',
    'adicionarTarefa'
];

foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        addResult('Funções', $func, 'success', "✅ Função disponível", '');
    } else {
        addResult('Funções', $func, 'warning', "⚠️ Função não encontrada (pode estar em admin_bot_ia.php)", '');
    }
}

// 7. Testar conexão com Gemini API
if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
    try {
        $testUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
        $testData = [
            'contents' => [
                ['parts' => [['text' => 'Responda apenas: OK']]]
            ]
        ];
        
        $ch = curl_init($testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            addResult('API Gemini', 'Conexão', 'error', "❌ Erro cURL: $curlError", '');
            $hasErrors = true;
        } else if ($httpCode === 200) {
            $apiResponse = json_decode($response, true);
            if (isset($apiResponse['candidates'][0]['content']['parts'][0]['text'])) {
                addResult('API Gemini', 'Conexão', 'success', "✅ API respondendo corretamente", "Resposta: " . substr($apiResponse['candidates'][0]['content']['parts'][0]['text'], 0, 50));
            } else {
                addResult('API Gemini', 'Conexão', 'warning', "⚠️ Resposta inesperada", substr($response, 0, 200));
            }
        } else if ($httpCode === 429) {
            addResult('API Gemini', 'Conexão', 'warning', "⚠️ Limite de requisições excedido", 'Aguarde alguns minutos');
        } else if ($httpCode === 400) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Erro desconhecido';
            addResult('API Gemini', 'Conexão', 'error', "❌ Erro 400: $errorMsg", '');
            $hasErrors = true;
        } else {
            addResult('API Gemini', 'Conexão', 'error', "❌ HTTP $httpCode", substr($response, 0, 200));
            $hasErrors = true;
        }
    } catch (Exception $e) {
        addResult('API Gemini', 'Conexão', 'error', "❌ Exception: " . $e->getMessage(), '');
        $hasErrors = true;
    }
}

// 8. Testar endpoint admin_bot_ia.php
try {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $testUrl = $protocol . '://' . $host . $scriptPath . '/admin_bot_ia.php';
    
    // Buscar um user_id válido se não houver sessão
    $testUserId = $userId;
    if (!$testUserId) {
        try {
            $stmt = $pdo->query("SELECT id FROM usuarios LIMIT 1");
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $testUserId = (int)$user['id'];
            } else {
                addResult('Endpoint', 'admin_bot_ia.php', 'error', "❌ Nenhum usuário encontrado no banco para teste", '');
                $hasErrors = true;
            }
        } catch (Exception $e) {
            addResult('Endpoint', 'admin_bot_ia.php', 'error', "❌ Erro ao buscar usuário: " . $e->getMessage(), '');
            $hasErrors = true;
        }
    }
    
    if (!$testUserId) {
        // Se não conseguiu obter um user_id válido, pular o teste
        addResult('Endpoint', 'admin_bot_ia.php', 'warning', "⚠️ Não foi possível obter user_id válido para teste", '');
    } else {
        $testData = [
            'pergunta' => 'teste',
            'user_id' => $testUserId
        ];
        
        $ch = curl_init($testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer site-financeiro-token-2024'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            addResult('Endpoint', 'admin_bot_ia.php', 'warning', "⚠️ Erro cURL: $curlError", "URL: $testUrl");
        } else if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if ($responseData && isset($responseData['resposta'])) {
                addResult('Endpoint', 'admin_bot_ia.php', 'success', "✅ Endpoint funcionando", "Resposta: " . substr($responseData['resposta'], 0, 100));
            } else {
                addResult('Endpoint', 'admin_bot_ia.php', 'warning', "⚠️ Resposta inválida", substr($response, 0, 200));
            }
        } else {
            addResult('Endpoint', 'admin_bot_ia.php', 'error', "❌ HTTP $httpCode", substr($response, 0, 200));
            $hasErrors = true;
        }
    }
} catch (Exception $e) {
    addResult('Endpoint', 'admin_bot_ia.php', 'error', "❌ Exception: " . $e->getMessage(), '');
    $hasErrors = true;
}

// 9. Verificar dados do usuário
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transacoes WHERE id_usuario = ?");
        $stmt->execute([$userId]);
        $transacoes = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
        $stmt->execute([$userId]);
        $tarefas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        addResult('Dados', 'Transações', 'success', "✅ " . $transacoes['total'] . " transações encontradas", '');
        addResult('Dados', 'Tarefas', 'success', "✅ " . $tarefas['total'] . " tarefas pendentes", '');
    } catch (Exception $e) {
        addResult('Dados', 'Consulta', 'warning', "⚠️ Erro: " . $e->getMessage(), '');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico IA WhatsApp Bot</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 15px;
            font-size: 0.9em;
        }
        
        .status-ok {
            background: #10b981;
            color: white;
        }
        
        .status-error {
            background: #ef4444;
            color: white;
        }
        
        .content {
            padding: 30px;
        }
        
        .category {
            margin-bottom: 30px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .category-header {
            background: #f9fafb;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 1.2em;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .test-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .test-item:last-child {
            border-bottom: none;
        }
        
        .test-icon {
            font-size: 1.5em;
            min-width: 30px;
        }
        
        .test-content {
            flex: 1;
        }
        
        .test-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 5px;
        }
        
        .test-message {
            color: #6b7280;
            font-size: 0.9em;
        }
        
        .test-details {
            margin-top: 8px;
            padding: 10px;
            background: #f3f4f6;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            color: #374151;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        
        .summary {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .summary-label {
            color: #6b7280;
            font-size: 0.9em;
        }
        
        .btn-refresh {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
            margin-top: 20px;
        }
        
        .btn-refresh:hover {
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            
            .test-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Diagnóstico IA WhatsApp Bot</h1>
            <p>Verificação completa do sistema de IA</p>
            <span class="status-badge <?php echo $hasErrors ? 'status-error' : 'status-ok'; ?>">
                <?php echo $hasErrors ? '⚠️ Problemas Detectados' : '✅ Tudo OK'; ?>
            </span>
        </div>
        
        <div class="content">
            <div class="summary">
                <?php
                $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
                $warningCount = count(array_filter($results, fn($r) => $r['status'] === 'warning'));
                $errorCount = count(array_filter($results, fn($r) => $r['status'] === 'error'));
                ?>
                <div class="summary-item">
                    <div class="summary-number" style="color: #10b981;"><?php echo $successCount; ?></div>
                    <div class="summary-label">Sucessos</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number" style="color: #f59e0b;"><?php echo $warningCount; ?></div>
                    <div class="summary-label">Avisos</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number" style="color: #ef4444;"><?php echo $errorCount; ?></div>
                    <div class="summary-label">Erros</div>
                </div>
            </div>
            
            <?php
            $currentCategory = '';
            foreach ($results as $result) {
                if ($result['category'] !== $currentCategory) {
                    if ($currentCategory !== '') {
                        echo '</div>';
                    }
                    $currentCategory = $result['category'];
                    echo '<div class="category">';
                    echo '<div class="category-header">' . htmlspecialchars($currentCategory) . '</div>';
                }
                
                $icon = '✅';
                $statusClass = 'success';
                if ($result['status'] === 'warning') {
                    $icon = '⚠️';
                    $statusClass = 'warning';
                } elseif ($result['status'] === 'error') {
                    $icon = '❌';
                    $statusClass = 'error';
                }
                
                echo '<div class="test-item">';
                echo '<div class="test-icon ' . $statusClass . '">' . $icon . '</div>';
                echo '<div class="test-content">';
                echo '<div class="test-name">' . htmlspecialchars($result['test']) . '</div>';
                echo '<div class="test-message">' . htmlspecialchars($result['message']) . '</div>';
                if (!empty($result['details'])) {
                    echo '<div class="test-details">' . htmlspecialchars($result['details']) . '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            if ($currentCategory !== '') {
                echo '</div>';
            }
            ?>
            
            <div style="text-align: center;">
                <a href="?" class="btn-refresh">🔄 Atualizar Diagnóstico</a>
            </div>
        </div>
    </div>
</body>
</html>

