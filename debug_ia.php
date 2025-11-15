<?php
// debug_ia.php - Página de Debug para o Assistente IA (Orion)

require_once 'templates/header.php';
require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';

$debugInfo = [];
$errors = [];
$warnings = [];
$success = [];

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
        addDebug('Configuração', 'API Key: ' . substr($apiKey, 0, 20) . '...', 'info');
    } else {
        addError('Configuração', 'GEMINI_API_KEY está definido mas vazio');
    }
} else {
    addError('Configuração', 'GEMINI_API_KEY não está definido');
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
} catch (Exception $e) {
    addError('Rate Limiter', 'Erro ao verificar rate limiter: ' . $e->getMessage());
}

// 5. Verificar funções disponíveis
$funcoesDisponiveis = [
    'getResumoFinanceiro',
    'getPrincipaisCategoriasGasto',
    'cadastrarTransacao',
    'getTarefasDoUsuario',
    'getTarefasUrgentes',
    'adicionarTarefa'
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
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTarefas = $result['total'] ?? 0;
    addDebug('Dados', "Total de tarefas pendentes: $totalTarefas", 'info');
    
    if ($totalTarefas > 0) {
        // Verificar tarefas urgentes
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ? AND status = 'pendente' AND (prioridade = 'Alta' OR (data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)))");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalUrgentes = $result['total'] ?? 0;
        addDebug('Dados', "Total de tarefas urgentes: $totalUrgentes", 'info');
    }
} catch (PDOException $e) {
    addError('Dados', 'Erro ao verificar tarefas: ' . $e->getMessage());
}

// 8. Testar requisição direta à API do Gemini (se API key estiver configurada)
if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
    addDebug('API', 'Testando conexão com API do Gemini...', 'info');
    
    $testUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . GEMINI_API_KEY;
    $testData = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => 'Teste de conexão']]
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
            addSuccess('API', 'Conexão com API do Gemini OK');
            addDebug('API', 'Resposta de teste recebida', 'info');
        } else {
            addWarning('API', 'API respondeu mas formato inesperado');
            addDebug('API', 'Resposta: ' . substr($response, 0, 200), 'warning');
        }
    } elseif ($httpCode === 429) {
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['error']['message'] ?? 'Rate limit excedido';
        addWarning('API', 'Rate limit da API: ' . $errorMsg);
        
        if (stripos($errorMsg, 'quota') !== false || stripos($errorMsg, 'limit: 0') !== false) {
            addError('API', 'COTA EXCEDIDA - A cota gratuita da API foi excedida');
        }
    } elseif ($httpCode === 403) {
        addError('API', 'Acesso negado (403) - Verifique se a API Key está correta e se a API está habilitada');
    } elseif ($httpCode === 401) {
        addError('API', 'Não autorizado (401) - API Key inválida');
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
    'buscar_tarefas_urgentes_direto.php' => 'Endpoint alternativo para tarefas urgentes',
    'includes/rate_limiter.php' => 'Rate limiter'
];

foreach ($arquivosNecessarios as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        addSuccess('Arquivos', "$descricao encontrado: $arquivo");
    } else {
        addError('Arquivos', "$descricao não encontrado: $arquivo");
    }
}

// 10. Testar função getTarefasUrgentes diretamente
if (file_exists('processar_analise_ia.php')) {
    require_once 'processar_analise_ia.php';
    
    if (function_exists('getTarefasUrgentes')) {
        try {
            $resultado = getTarefasUrgentes($pdo, $userId);
            if (isset($resultado['tarefas_urgentes'])) {
                $total = count($resultado['tarefas_urgentes']);
                addSuccess('Teste', "Função getTarefasUrgentes executada com sucesso - $total tarefas encontradas");
                if ($total > 0) {
                    addDebug('Teste', 'Primeira tarefa: ' . $resultado['tarefas_urgentes'][0]['descricao'], 'info');
                }
            } elseif (isset($resultado['resultado'])) {
                addSuccess('Teste', "Função getTarefasUrgentes executada: " . $resultado['resultado']);
            }
        } catch (Exception $e) {
            addError('Teste', 'Erro ao executar getTarefasUrgentes: ' . $e->getMessage());
        }
    } else {
        addWarning('Teste', 'Função getTarefasUrgentes não está disponível no escopo global');
    }
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .debug-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 1.5rem;
        }
        .debug-item {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .debug-item.success {
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
            color: #155724;
        }
        .debug-item.error {
            background: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
            color: #721c24;
        }
        .debug-item.warning {
            background: rgba(255, 193, 7, 0.1);
            border-color: #ffc107;
            color: #856404;
        }
        .debug-item.info {
            background: rgba(23, 162, 184, 0.1);
            border-color: #17a2b8;
            color: #0c5460;
        }
        .stats-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        .test-section {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card debug-card">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0">
                            <i class="bi bi-robot me-2"></i>Debug - Assistente IA (Orion)
                        </h2>
                        <small>Análise completa do sistema de IA e diagnóstico de problemas</small>
                    </div>
                    <div class="card-body">
                        <!-- Estatísticas -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="mb-0 text-success"><?php echo count($success); ?></h3>
                                        <small>Sucessos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="mb-0 text-warning"><?php echo count($warnings); ?></h3>
                                        <small>Avisos</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="mb-0 text-danger"><?php echo count($errors); ?></h3>
                                        <small>Erros</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stats-card text-center">
                                    <div class="card-body">
                                        <h3 class="mb-0 text-info"><?php echo count($debugInfo); ?></h3>
                                        <small>Total</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ações Rápidas -->
                        <?php if (count($errors) > 0): ?>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Problemas Encontrados</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><strong><?php echo htmlspecialchars($error['category']); ?>:</strong> <?php echo htmlspecialchars($error['message']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <!-- Log de Debug -->
                        <h5 class="mt-4 mb-3"><i class="bi bi-list-ul me-2"></i>Log de Debug</h5>
                        <div class="debug-log" style="max-height: 600px; overflow-y: auto;">
                            <?php 
                            $lastCategory = '';
                            foreach ($debugInfo as $item): 
                                if ($lastCategory !== $item['category']):
                                    if ($lastCategory !== ''):
                                        echo '</div></div>';
                                    endif;
                                    echo '<div class="mb-3">';
                                    echo '<h6 class="text-muted"><i class="bi bi-folder me-2"></i>' . htmlspecialchars($item['category']) . '</h6>';
                                    echo '<div class="ms-3">';
                                    $lastCategory = $item['category'];
                                endif;
                            ?>
                                <div class="debug-item <?php echo $item['type']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <i class="bi <?php 
                                                echo $item['type'] === 'success' ? 'bi-check-circle' : 
                                                    ($item['type'] === 'error' ? 'bi-x-circle' : 
                                                    ($item['type'] === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle')); 
                                            ?> me-2"></i>
                                            <?php echo htmlspecialchars($item['message']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo $item['time']; ?></small>
                                    </div>
                                </div>
                            <?php 
                            endforeach; 
                            if ($lastCategory !== ''):
                                echo '</div></div>';
                            endif;
                            ?>
                        </div>

                        <!-- Seção de Teste -->
                        <div class="test-section">
                            <h5 class="mb-3"><i class="bi bi-play-circle me-2"></i>Teste Rápido</h5>
                            <p class="text-muted">Teste o assistente IA com uma pergunta simples:</p>
                            <div class="d-flex gap-2 mb-3">
                                <input type="text" id="testPergunta" class="form-control" placeholder="Digite uma pergunta de teste..." value="Quais são minhas tarefas mais urgentes?">
                                <button class="btn btn-primary" id="btnTestar">
                                    <i class="bi bi-play-fill me-2"></i>Testar
                                </button>
                            </div>
                            <div id="testResult" class="mt-3" style="display: none;">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Resultado do Teste:</h6>
                                        <div id="testResultContent"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <a href="analista_ia.php" class="btn btn-primary">
                                <i class="bi bi-robot me-2"></i>Ir para Assistente IA
                            </a>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
                            </a>
                            <button onclick="window.location.reload()" class="btn btn-info">
                                <i class="bi bi-arrow-clockwise me-2"></i>Recarregar Debug
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
            if (!pergunta) {
                alert('Digite uma pergunta para testar');
                return;
            }
            
            btnTestar.disabled = true;
            btnTestar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testando...';
            testResult.style.display = 'block';
            testResultContent.innerHTML = '<div class="spinner-border spinner-border-sm text-primary"></div> Testando...';
            
            fetch('processar_analise_ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pergunta: pergunta })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    testResultContent.innerHTML = '<div class="alert alert-success"><strong>Sucesso!</strong></div>' + 
                        '<div class="mt-2">' + marked.parse(data.resposta) + '</div>';
                } else {
                    testResultContent.innerHTML = '<div class="alert alert-danger"><strong>Erro:</strong> ' + 
                        (data.message || 'Erro desconhecido') + '</div>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                testResultContent.innerHTML = '<div class="alert alert-danger"><strong>Erro de Rede:</strong> ' + 
                    error.message + '</div>';
            })
            .finally(() => {
                btnTestar.disabled = false;
                btnTestar.innerHTML = '<i class="bi bi-play-fill me-2"></i>Testar';
            });
        });
    });
    </script>
</body>
</html>
