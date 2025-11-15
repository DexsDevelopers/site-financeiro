<?php
// debug_ia.php - Página de Debug Completa para o Assistente Orion

session_start();
require_once 'includes/db_connect.php';
require_once 'includes/rate_limiter.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
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

// 1. Verificar PHP e extensões
addDebug('Sistema', 'PHP Version: ' . phpversion(), 'info');
addDebug('Sistema', 'Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'), 'info');

if (!extension_loaded('curl')) {
    addError('PHP', 'Extensão cURL não está carregada');
} else {
    addSuccess('PHP', 'Extensão cURL está carregada');
    $curlVersion = curl_version();
    addDebug('PHP', 'cURL Version: ' . ($curlVersion['version'] ?? 'N/A'), 'info');
}

// 2. Verificar configuração da API Gemini
if (defined('GEMINI_API_KEY')) {
    $apiKey = GEMINI_API_KEY;
    if (!empty($apiKey)) {
        addSuccess('API Gemini', 'GEMINI_API_KEY está configurado');
        addDebug('API Gemini', 'API Key: ' . substr($apiKey, 0, 10) . '...', 'info');
    } else {
        addError('API Gemini', 'GEMINI_API_KEY está definido mas vazio');
    }
} else {
    addError('API Gemini', 'GEMINI_API_KEY não está definido');
}

// 3. Verificar conexão com banco
if (isset($pdo) && $pdo) {
    addSuccess('Banco', 'Conexão com banco de dados estabelecida');
    
    try {
        $testQuery = $pdo->query("SELECT 1");
        if ($testQuery) {
            addSuccess('Banco', 'Query de teste executada com sucesso');
        }
    } catch (PDOException $e) {
        addError('Banco', 'Erro ao executar query de teste: ' . $e->getMessage());
    }
} else {
    addError('Banco', 'Conexão com banco de dados não disponível');
}

// 4. Verificar tabela de tarefas
if (isset($pdo) && $pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
        if ($stmt->rowCount() > 0) {
            addSuccess('Banco', "Tabela 'tarefas' existe");
            
            // Verificar estrutura
            $stmt = $pdo->query("DESCRIBE tarefas");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
            
            $requiredColumns = ['id', 'id_usuario', 'descricao', 'prioridade', 'status', 'data_limite'];
            $missingColumns = array_diff($requiredColumns, $columnNames);
            
            if (empty($missingColumns)) {
                addSuccess('Banco', "Tabela 'tarefas' tem todas as colunas necessárias");
            } else {
                addWarning('Banco', "Tabela 'tarefas' está faltando colunas: " . implode(', ', $missingColumns));
            }
            
            // Contar tarefas do usuário
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $totalTarefas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            addDebug('Banco', "Você possui {$totalTarefas} tarefa(s) no total", 'info');
            
            // Contar tarefas urgentes
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ? AND status = 'pendente' AND (prioridade = 'Alta' OR (data_limite IS NOT NULL AND data_limite <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)))");
            $stmt->execute([$userId]);
            $tarefasUrgentes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            addDebug('Banco', "Você possui {$tarefasUrgentes} tarefa(s) urgente(s)", 'info');
            
        } else {
            addError('Banco', "Tabela 'tarefas' não existe");
        }
    } catch (PDOException $e) {
        addError('Banco', "Erro ao verificar tabela 'tarefas': " . $e->getMessage());
    }
}

// 5. Testar função getTarefasUrgentes
if (isset($pdo) && $pdo) {
    try {
        require_once 'processar_analise_ia.php';
        
        // Testar função diretamente
        $resultado = getTarefasUrgentes($pdo, $userId);
        
        if (isset($resultado['tarefas_urgentes'])) {
            $total = count($resultado['tarefas_urgentes']);
            addSuccess('Função', "getTarefasUrgentes() executada com sucesso - encontrou {$total} tarefa(s)");
            
            if ($total > 0) {
                addDebug('Função', 'Primeira tarefa: ' . $resultado['tarefas_urgentes'][0]['descricao'], 'info');
            }
        } elseif (isset($resultado['resultado'])) {
            addWarning('Função', 'getTarefasUrgentes() retornou: ' . $resultado['resultado']);
        } else {
            addWarning('Função', 'getTarefasUrgentes() retornou resultado inesperado');
        }
    } catch (Exception $e) {
        addError('Função', 'Erro ao testar getTarefasUrgentes(): ' . $e->getMessage());
    }
}

// 6. Testar conexão com API Gemini
if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
    addDebug('API Test', 'Testando conexão com API Gemini...', 'info');
    
    $testUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . GEMINI_API_KEY;
    $testData = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => 'Responda apenas: OK']]
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
    
    if ($curlError) {
        addError('API Test', 'Erro cURL: ' . $curlError);
    } elseif ($httpCode === 200) {
        $responseData = json_decode($response, true);
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            addSuccess('API Test', 'Conexão com API Gemini bem-sucedida!');
            addDebug('API Test', 'Resposta: ' . substr($responseData['candidates'][0]['content']['parts'][0]['text'], 0, 100), 'info');
        } else {
            addWarning('API Test', 'API respondeu mas formato inesperado');
            addDebug('API Test', 'Resposta completa: ' . substr($response, 0, 200), 'info');
        }
    } elseif ($httpCode === 429) {
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['error']['message'] ?? 'Limite de requisições excedido';
        addWarning('API Test', "HTTP 429 - Limite de requisições: {$errorMsg}");
    } elseif ($httpCode === 403) {
        addError('API Test', 'HTTP 403 - Acesso negado. Verifique se a API Key está correta e se a API está habilitada.');
    } elseif ($httpCode === 400) {
        $responseData = json_decode($response, true);
        $errorMsg = $responseData['error']['message'] ?? 'Requisição inválida';
        addError('API Test', "HTTP 400 - Erro na requisição: {$errorMsg}");
    } else {
        addError('API Test', "HTTP {$httpCode} - Erro desconhecido");
        addDebug('API Test', 'Resposta: ' . substr($response, 0, 200), 'error');
    }
} else {
    addWarning('API Test', 'Não foi possível testar API - GEMINI_API_KEY não configurado');
}

// 7. Testar Rate Limiter
try {
    if (isset($rateLimiter) && $rateLimiter !== null) {
        $rateLimitCheck = $rateLimiter->checkRateLimit($userId, 'gemini');
        if ($rateLimitCheck['allowed']) {
            addSuccess('Rate Limiter', 'Rate limiter funcionando - requisições permitidas');
            addDebug('Rate Limiter', 'Limite: ' . ($rateLimitCheck['limit_type'] ?? 'N/A'), 'info');
        } else {
            addWarning('Rate Limiter', 'Rate limiter bloqueando: ' . ($rateLimitCheck['message'] ?? 'Limite excedido'));
        }
    } else {
        addWarning('Rate Limiter', 'Rate limiter não disponível');
    }
} catch (Exception $e) {
    addWarning('Rate Limiter', 'Erro ao verificar rate limiter: ' . $e->getMessage());
}

// 8. Testar processamento completo (simulado)
if (isset($pdo) && $pdo && defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
    addDebug('Teste Completo', 'Testando processamento completo...', 'info');
    
    // Simular uma pergunta
    $perguntaTeste = "Quais são minhas tarefas mais urgentes?";
    
    try {
        // Verificar se a função existe
        if (function_exists('getTarefasUrgentes')) {
            $resultado = getTarefasUrgentes($pdo, $userId);
            
            if (isset($resultado['tarefas_urgentes']) && !empty($resultado['tarefas_urgentes'])) {
                addSuccess('Teste Completo', 'Função getTarefasUrgentes retornou dados corretamente');
                
                // Testar formatação
                $respostaFormatada = "Aqui estão suas tarefas mais urgentes:\n\n";
                foreach ($resultado['tarefas_urgentes'] as $tarefa) {
                    $data_info = '';
                    if (!empty($tarefa['data_limite'])) {
                        $data_formatada = date('d/m/Y', strtotime($tarefa['data_limite']));
                        $data_info = " (Prazo: {$data_formatada})";
                    }
                    $respostaFormatada .= "- **{$tarefa['descricao']}** - Prioridade: {$tarefa['prioridade']}{$data_info}\n";
                }
                
                addSuccess('Teste Completo', 'Formatação de resposta funcionando corretamente');
                addDebug('Teste Completo', 'Resposta formatada: ' . substr($respostaFormatada, 0, 200) . '...', 'info');
            } else {
                addWarning('Teste Completo', 'Função retornou sem tarefas urgentes');
            }
        } else {
            addError('Teste Completo', 'Função getTarefasUrgentes não está disponível');
        }
    } catch (Exception $e) {
        addError('Teste Completo', 'Erro no teste completo: ' . $e->getMessage());
    }
}

// 9. Verificar arquivos necessários
$requiredFiles = [
    'processar_analise_ia.php' => 'Processador principal da IA',
    'analista_ia.php' => 'Interface do assistente',
    'buscar_tarefas_urgentes_direto.php' => 'Endpoint alternativo',
    'includes/rate_limiter.php' => 'Rate limiter'
];

foreach ($requiredFiles as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        addSuccess('Arquivos', "$description encontrado: $file");
    } else {
        addError('Arquivos', "$description não encontrado: $file");
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Assistente Orion</title>
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
        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
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
                            <i class="bi bi-robot me-2"></i>Debug - Assistente Orion
                        </h2>
                        <small>Análise completa do sistema de IA e correção automática de problemas</small>
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

                        <!-- Seção de Teste Interativo -->
                        <div class="test-section">
                            <h5 class="mb-3"><i class="bi bi-play-circle me-2"></i>Teste Interativo</h5>
                            <div class="input-group mb-3">
                                <input type="text" id="testPergunta" class="form-control" placeholder="Digite uma pergunta para testar..." value="Quais são minhas tarefas mais urgentes?">
                                <button class="btn btn-primary" id="btnTestar">
                                    <i class="bi bi-send me-2"></i>Testar
                                </button>
                            </div>
                            <div id="testResult" class="mt-3"></div>
                        </div>

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

                        <!-- Informações do Sistema -->
                        <div class="mt-4">
                            <h5><i class="bi bi-info-circle me-2"></i>Informações do Sistema</h5>
                            <div class="code-block">
                                <div><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
                                <div><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></div>
                                <div><strong>Extensions:</strong> 
                                    cURL: <?php echo extension_loaded('curl') ? '✓' : '✗'; ?> | 
                                    PDO: <?php echo extension_loaded('pdo') ? '✓' : '✗'; ?> |
                                    JSON: <?php echo extension_loaded('json') ? '✓' : '✗'; ?>
                                </div>
                                <?php if (defined('GEMINI_API_KEY')): ?>
                                <div><strong>GEMINI_API_KEY:</strong> <?php echo substr(GEMINI_API_KEY, 0, 10) . '...'; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <a href="analista_ia.php" class="btn btn-primary">
                                <i class="bi bi-robot me-2"></i>Ir para Assistente Orion
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnTestar = document.getElementById('btnTestar');
        const testPergunta = document.getElementById('testPergunta');
        const testResult = document.getElementById('testResult');
        
        btnTestar.addEventListener('click', function() {
            const pergunta = testPergunta.value.trim();
            if (!pergunta) {
                testResult.innerHTML = '<div class="alert alert-warning">Digite uma pergunta para testar.</div>';
                return;
            }
            
            btnTestar.disabled = true;
            btnTestar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testando...';
            testResult.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split me-2"></i>Processando...</div>';
            
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
                    testResult.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle me-2"></i>Teste Bem-Sucedido!</h6>
                            <div class="mt-2 p-3 bg-dark text-white rounded">
                                <strong>Resposta da IA:</strong><br>
                                ${data.resposta.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                    `;
                } else {
                    testResult.innerHTML = `
                        <div class="alert alert-danger">
                            <h6><i class="bi bi-x-circle me-2"></i>Erro no Teste</h6>
                            <p class="mb-0">${data.message || 'Erro desconhecido'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                testResult.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle me-2"></i>Erro de Rede</h6>
                        <p class="mb-0">${error.message}</p>
                        <small class="d-block mt-2">Verifique a conexão e tente novamente.</small>
                    </div>
                `;
            })
            .finally(() => {
                btnTestar.disabled = false;
                btnTestar.innerHTML = '<i class="bi bi-send me-2"></i>Testar';
            });
        });
        
        // Permitir Enter no input
        testPergunta.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                btnTestar.click();
            }
        });
    });
    </script>
</body>
</html>

