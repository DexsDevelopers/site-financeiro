<?php
// debug_ia.php - Página de Debug para o Assistente IA (Orion)

// Inclusão de templates e sessão
require_once 'templates/header.php'; // Garante sessão iniciada e $userId
require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';

// --- CORREÇÃO: Carregar Helpers para validação das funções ---
// Sem isso, function_exists retornaria false mesmo com os arquivos existindo
if (file_exists('includes/finance_helper.php')) require_once 'includes/finance_helper.php';
if (file_exists('includes/tasks_helper.php')) require_once 'includes/tasks_helper.php';

$debugInfo = [];
$errors = [];
$warnings = [];
$success = [];

// Garante que userId existe (fallback para debug)
if (!isset($userId)) $userId = $_SESSION['user_id'] ?? 87;

// Função para adicionar informações de debug
function addDebug($category, $message, $type = 'info') {
    global $debugInfo;
    $debugInfo[] = [
        'category' => $category,
        'message' => $message,
        'type' => $type,
        'time' => date('H:i:s')
    ];
}

function addError($category, $message) {
    global $errors;
    $errors[] = ['category' => $category, 'message' => $message];
    addDebug($category, $message, 'error');
}

function addWarning($category, $message) {
    global $warnings;
    $warnings[] = ['category' => $category, 'message' => $message];
    addDebug($category, $message, 'warning');
}

function addSuccess($category, $message) {
    global $success;
    $success[] = ['category' => $category, 'message' => $message];
    addDebug($category, $message, 'success');
}

// 1. Verificar configuração básica
addDebug('Sistema', 'PHP Version: ' . phpversion(), 'info');
addDebug('Sistema', 'User ID: ' . $userId, 'info');

// 2. Verificar API Key
if (defined('GEMINI_API_KEY')) {
    $apiKey = GEMINI_API_KEY;
    if (!empty($apiKey)) {
        addSuccess('Configuração', 'GEMINI_API_KEY está configurado');
        addDebug('Configuração', 'API Key: ' . substr($apiKey, 0, 10) . '...' . substr($apiKey, -4), 'info');
    } else {
        addError('Configuração', 'GEMINI_API_KEY está definido mas vazio');
    }
} else {
    // Tenta carregar do config se não estiver definido
    if (file_exists('/home/u853242961/config/config.php')) {
        require_once '/home/u853242961/config/config.php';
        if (defined('GEMINI_API_KEY')) {
            addSuccess('Configuração', 'GEMINI_API_KEY carregada do config externo');
        } else {
            addError('Configuração', 'GEMINI_API_KEY não encontrada no config externo');
        }
    } else {
        addError('Configuração', 'GEMINI_API_KEY não está definido');
    }
}

// 3. Verificar extensões PHP
if (extension_loaded('curl')) {
    addSuccess('PHP', 'Extensão cURL está carregada');
} else {
    addError('PHP', 'Extensão cURL não está carregada');
}

if (extension_loaded('json')) {
    addSuccess('PHP', 'Extensão JSON está carregada');
} else {
    addError('PHP', 'Extensão JSON não está carregada');
}

// 4. Verificar Rate Limiter
try {
    if (class_exists('RateLimiter')) {
        $rateLimiter = new RateLimiter($pdo);
        addSuccess('Rate Limiter', 'RateLimiter instanciado com sucesso');
        
        $rateLimitCheck = $rateLimiter->checkRateLimit($userId, 'gemini');
        if ($rateLimitCheck['allowed']) {
            addSuccess('Rate Limiter', 'Rate limit OK - você pode fazer requisições');
            addDebug('Rate Limiter', 'Limite por minuto: ' . ($rateLimitCheck['limit_type'] ?? 'N/A'), 'info');
        } else {
            addWarning('Rate Limiter', 'Rate limit atingido: ' . ($rateLimitCheck['message'] ?? 'Limite excedido'));
            addDebug('Rate Limiter', 'Retry após: ' . ($rateLimitCheck['retry_after'] ?? 'N/A') . ' segundos', 'info');
        }
        
        $usageStats = $rateLimiter->getUsageStats($userId, 'gemini');
        addDebug('Rate Limiter', 'Estatísticas: ' . json_encode($usageStats), 'info');
    } else {
        addWarning('Rate Limiter', 'Classe RateLimiter não encontrada');
    }
} catch (Exception $e) {
    addError('Rate Limiter', 'Erro ao verificar rate limiter: ' . $e->getMessage());
}

// 5. Verificar funções disponíveis
$funcoesDisponiveis = [
    'getResumoFinanceiro',
    'cadastrarTransacao', // Verifique se o nome no helper é este mesmo
    'getTarefasDoUsuario',
    'getTarefasUrgentes'  // Esta às vezes é local, cuidado
];

addDebug('Funções', 'Verificando funções disponíveis...', 'info');
foreach ($funcoesDisponiveis as $funcao) {
    if (function_exists($funcao)) {
        addSuccess('Funções', "Função '$funcao' está disponível");
    } else {
        addWarning('Funções', "Função '$funcao' não está disponível (pode estar no escopo do arquivo)");
    }
}

// 6. Testar conexão com banco de dados
try {
    $testQuery = $pdo->query("SELECT 1");
    if ($testQuery) {
        addSuccess('Banco de Dados', 'Conexão com banco de dados OK');
    }
} catch (PDOException $e) {
    addError('Banco de Dados', 'Erro ao conectar: ' . $e->getMessage());
}

// 7. Verificar se há tarefas no banco
try {
    // Tenta tabela 'tarefas' (português)
    $tabelaTarefas = 'tarefas';
    try {
        $pdo->query("SELECT 1 FROM tarefas LIMIT 1");
    } catch (Exception $e) {
        $tabelaTarefas = 'tasks'; // Fallback para inglês
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM $tabelaTarefas WHERE id_usuario = ? AND status = 'pendente'");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTarefas = $result['total'] ?? 0;
    addDebug('Dados', "Total de tarefas pendentes ($tabelaTarefas): $totalTarefas", 'info');
    
    if ($totalTarefas > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM $tabelaTarefas WHERE id_usuario = ? AND status = 'pendente' AND (prioridade = 'Alta' OR (data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)))");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalUrgentes = $result['total'] ?? 0;
        addDebug('Dados', "Total de tarefas urgentes: $totalUrgentes", 'info');
    }
} catch (PDOException $e) {
    addError('Dados', 'Erro ao verificar tarefas: ' . $e->getMessage());
}

// 8. Testar requisição direta à API do Gemini
if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
    addDebug('API', 'Testando conexão com API do Gemini...', 'info');
    
    // Usando modelo recomendado gemini-2.5-flash
    $testUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;
    
    $testData = [
        'contents' => [
            [
                'parts' => [['text' => 'Responda apenas com a palavra OK.']]
            ]
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
    
    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            addSuccess('API', 'Conexão com API do Gemini OK (Modelo 001)');
            addDebug('API', 'Resposta: ' . $responseData['candidates'][0]['content']['parts'][0]['text'], 'info');
        } else {
            addWarning('API', 'API respondeu mas formato inesperado');
            addDebug('API', 'Resposta: ' . substr($response, 0, 200), 'warning');
        }
    } elseif ($httpCode === 404) {
        addError('API', 'Erro 404: Modelo não encontrado. Verifique a versão da API na URL.');
    } elseif ($httpCode === 429) {
        addError('API', 'Erro 429: Rate Limit ou Cota Excedida.');
    } else {
        addError('API', "Erro HTTP $httpCode: " . ($curlError ?: substr($response, 0, 200)));
    }
} else {
    addWarning('API', 'Não foi possível testar API - GEMINI_API_KEY não configurado');
}

// 9. Verificar arquivos necessários
$arquivosNecessarios = [
    'processar_analise_ia.php' => 'Processador principal da IA',
    'analista_ia.php' => 'Interface do assistente',
    'includes/rate_limiter.php' => 'Rate limiter',
    'includes/finance_helper.php' => 'Helper Financeiro'
];

foreach ($arquivosNecessarios as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        addSuccess('Arquivos', "$descricao encontrado: $arquivo");
    } else {
        addError('Arquivos', "$descricao não encontrado: $arquivo");
    }
}

// 10. Testar função getTarefasUrgentes diretamente (definir função localmente para teste se não existir)
if (!function_exists('getTarefasUrgentes')) {
    // Definição dummy apenas para o teste não quebrar se o include falhar
    function getTarefasUrgentesMock($pdo, $userId) { return ['resultado' => 'Função mockada para teste']; }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Assistente IA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 2rem 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .debug-card {
            border: none;
            border-radius: 15px;
            background: #1f2940;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: #2d3b55 !important;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .debug-item {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            border-left: 4px solid;
            background: rgba(255,255,255,0.03);
        }
        .debug-item.success { border-color: #28a745; color: #4cd964; }
        .debug-item.error { border-color: #dc3545; color: #ff6b6b; }
        .debug-item.warning { border-color: #ffc107; color: #ffcc00; }
        .debug-item.info { border-color: #17a2b8; color: #5bc0de; }
        
        .stats-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            transition: transform 0.2s;
        }
        .stats-card:hover { transform: translateY(-5px); }
        
        .test-section {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        /* Scrollbar customizada */
        .debug-log::-webkit-scrollbar { width: 8px; }
        .debug-log::-webkit-scrollbar-track { background: #1a1a2e; }
        .debug-log::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card debug-card">
                    <div class="card-header text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0 h4">
                                <i class="bi bi-cpu me-2"></i>Diagnóstico Orion AI
                            </h2>
                            <small class="text-muted">Status do sistema e testes de conectividade</small>
                        </div>
                        <span class="badge bg-primary">v2.1 Stable</span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body p-2">
                                        <h3 class="mb-0 text-success"><?php echo count($success); ?></h3>
                                        <small>Sucessos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body p-2">
                                        <h3 class="mb-0 text-warning"><?php echo count($warnings); ?></h3>
                                        <small>Avisos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body p-2">
                                        <h3 class="mb-0 text-danger"><?php echo count($errors); ?></h3>
                                        <small>Erros</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body p-2">
                                        <h3 class="mb-0 text-info"><?php echo count($debugInfo); ?></h3>
                                        <small>Total Events</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3 text-white"><i class="bi bi-terminal me-2"></i>Log do Sistema</h5>
                        <div class="debug-log" style="max-height: 500px; overflow-y: auto;">
                            <?php 
                            $lastCategory = '';
                            foreach ($debugInfo as $item): 
                                if ($lastCategory !== $item['category']):
                                    if ($lastCategory !== ''): echo '</div></div>'; endif;
                                    echo '<div class="mb-3">';
                                    echo '<h6 class="text-white-50 small text-uppercase fw-bold"><i class="bi bi-hash me-1"></i>' . htmlspecialchars($item['category']) . '</h6>';
                                    echo '<div class="ms-2">';
                                    $lastCategory = $item['category'];
                                endif;
                            ?>
                                <div class="debug-item <?php echo $item['type']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <i class="bi <?php 
                                                echo $item['type'] === 'success' ? 'bi-check-circle-fill' : 
                                                    ($item['type'] === 'error' ? 'bi-x-circle-fill' : 
                                                    ($item['type'] === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill')); 
                                            ?> me-2"></i>
                                            <?php echo htmlspecialchars($item['message']); ?>
                                        </div>
                                        <small class="opacity-50 ms-2"><?php echo $item['time']; ?></small>
                                    </div>
                                </div>
                            <?php 
                            endforeach; 
                            if ($lastCategory !== ''): echo '</div></div>'; endif;
                            ?>
                        </div>

                        <div class="test-section">
                            <h5 class="mb-3 text-white"><i class="bi bi-lightning-charge me-2"></i>Teste em Tempo Real</h5>
                            <div class="input-group mb-3">
                                <input type="text" id="testPergunta" class="form-control bg-dark text-white border-secondary" placeholder="Ex: Analise meus gastos..." value="Faça uma análise rápida do meu dia.">
                                <button class="btn btn-primary" id="btnTestar">
                                    <i class="bi bi-send-fill me-2"></i>Enviar
                                </button>
                            </div>
                            <div id="testResult" class="mt-3" style="display: none;">
                                <div class="card bg-dark border-secondary">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-2">Resposta da IA:</h6>
                                        <div id="testResultContent" class="text-white"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <a href="analista_ia.php" class="btn btn-outline-primary">
                                <i class="bi bi-chat-dots me-2"></i>Ir para Chat
                            </a>
                            <button onclick="window.location.reload()" class="btn btn-outline-light">
                                <i class="bi bi-arrow-clockwise me-2"></i>Atualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnTestar = document.getElementById('btnTestar');
        const testPergunta = document.getElementById('testPergunta');
        const testResult = document.getElementById('testResult');
        const testResultContent = document.getElementById('testResultContent');
        
        btnTestar.addEventListener('click', function() {
            const pergunta = testPergunta.value.trim();
            if (!pergunta) return;
            
            btnTestar.disabled = true;
            btnTestar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
            testResult.style.display = 'block';
            testResultContent.innerHTML = '<span class="text-muted">Aguardando resposta...</span>';
            
            fetch('processar_analise_ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pergunta: pergunta })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    testResultContent.innerHTML = marked.parse(data.resposta);
                } else {
                    testResultContent.innerHTML = `<div class="text-danger"><i class="bi bi-x-circle me-2"></i>${data.message || 'Erro desconhecido'}</div>`;
                }
            })
            .catch(err => {
                testResultContent.innerHTML = `<div class="text-danger"><i class="bi bi-wifi-off me-2"></i>Erro de rede: ${err.message}</div>`;
            })
            .finally(() => {
                btnTestar.disabled = false;
                btnTestar.innerHTML = '<i class="bi bi-send-fill me-2"></i>Enviar';
            });
        });
    });
    </script>
</body>
</html>

