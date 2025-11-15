<?php
// debug_google_integration.php - Página de Debug Completa para Integração Google

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Desabilitar exibição de erros para capturar manualmente
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

// Função para adicionar erro
function addError($category, $message) {
    global $errors;
    $errors[] = ['category' => $category, 'message' => $message];
    addDebug($category, $message, 'error');
}

// Função para adicionar warning
function addWarning($category, $message) {
    global $warnings;
    $warnings[] = ['category' => $category, 'message' => $message];
    addDebug($category, $message, 'warning');
}

// Função para adicionar sucesso
function addSuccess($category, $message) {
    global $success;
    $success[] = ['category' => $category, 'message' => $message];
    addDebug($category, $message, 'success');
}

// 1. Verificar PHP e extensões
addDebug('Sistema', 'PHP Version: ' . phpversion(), 'info');
addDebug('Sistema', 'Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A'), 'info');
addDebug('Sistema', 'Document Root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A'), 'info');
addDebug('Sistema', 'Script Path: ' . __FILE__, 'info');

if (!extension_loaded('pdo')) {
    addError('PHP', 'Extensão PDO não está carregada');
} else {
    addSuccess('PHP', 'Extensão PDO está carregada');
}

if (!extension_loaded('pdo_mysql')) {
    addError('PHP', 'Extensão PDO_MySQL não está carregada');
} else {
    addSuccess('PHP', 'Extensão PDO_MySQL está carregada');
}

// 2. Verificar arquivos necessários
$requiredFiles = [
    'includes/db_connect.php' => 'Arquivo de conexão com banco',
    'includes/google_integration_manager.php' => 'Gerenciador de integração Google',
    'includes/google_oauth_config.php' => 'Configuração OAuth (não versionado)',
    'google_oauth_callback.php' => 'Callback OAuth',
    'integracoes_google.php' => 'Página de integrações',
    'criar_tabelas_google_integration.php' => 'Script de criação de tabelas'
];

foreach ($requiredFiles as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        addSuccess('Arquivos', "$description encontrado: $file");
        if (is_readable($fullPath)) {
            addSuccess('Arquivos', "$file é legível");
        } else {
            addError('Arquivos', "$file existe mas não é legível");
        }
    } else {
        addError('Arquivos', "$description não encontrado: $file");
    }
}

// 3. Verificar credenciais Google OAuth
$oauthConfigPath = __DIR__ . '/includes/google_oauth_config.php';
if (file_exists($oauthConfigPath)) {
    addSuccess('OAuth', 'Arquivo de configuração OAuth encontrado');
    
    // Tentar carregar
    try {
        require_once $oauthConfigPath;
        
        if (defined('GOOGLE_CLIENT_ID')) {
            $clientId = GOOGLE_CLIENT_ID;
            if (!empty($clientId)) {
                addSuccess('OAuth', 'GOOGLE_CLIENT_ID está configurado');
                addDebug('OAuth', 'Client ID: ' . substr($clientId, 0, 20) . '...', 'info');
            } else {
                addError('OAuth', 'GOOGLE_CLIENT_ID está definido mas vazio');
            }
        } else {
            addError('OAuth', 'GOOGLE_CLIENT_ID não está definido');
        }
        
        if (defined('GOOGLE_CLIENT_SECRET')) {
            $clientSecret = GOOGLE_CLIENT_SECRET;
            if (!empty($clientSecret)) {
                addSuccess('OAuth', 'GOOGLE_CLIENT_SECRET está configurado');
                addDebug('OAuth', 'Client Secret: ' . substr($clientSecret, 0, 10) . '...', 'info');
            } else {
                addError('OAuth', 'GOOGLE_CLIENT_SECRET está definido mas vazio');
            }
        } else {
            addError('OAuth', 'GOOGLE_CLIENT_SECRET não está definido');
        }
    } catch (Exception $e) {
        addError('OAuth', 'Erro ao carregar configuração: ' . $e->getMessage());
    }
} else {
    addError('OAuth', 'Arquivo de configuração OAuth não encontrado');
    addWarning('OAuth', 'Crie o arquivo includes/google_oauth_config.php baseado em includes/google_oauth_config.php.example');
}

// 4. Testar conexão com banco de dados
// Limpar variáveis antes de testar
unset($pdo);
unset($db_connect_error);
unset($db_connect_error_code);

$dbError = null;
$dbConnectPath = __DIR__ . '/includes/db_connect.php';

if (file_exists($dbConnectPath)) {
    addSuccess('Banco', 'Arquivo db_connect.php encontrado');
    
    try {
        // Capturar qualquer output antes do require
        ob_start();
        $oldErrorReporting = error_reporting(E_ALL);
        $oldDisplayErrors = ini_set('display_errors', 0);
        
        // Incluir o arquivo
        if (!@include_once $dbConnectPath) {
            $includeError = error_get_last();
            ob_end_clean();
            if ($includeError) {
                addError('Banco', 'Erro ao incluir db_connect.php: ' . $includeError['message']);
            } else {
                addError('Banco', 'Erro ao incluir db_connect.php: arquivo não encontrado ou não pode ser incluído');
            }
            error_reporting($oldErrorReporting);
            if ($oldDisplayErrors !== false) {
                ini_set('display_errors', $oldDisplayErrors);
            }
        } else {
            $output = ob_get_clean();
            error_reporting($oldErrorReporting);
            if ($oldDisplayErrors !== false) {
                ini_set('display_errors', $oldDisplayErrors);
            }
            
            if (!empty($output)) {
                // Limpar output se for apenas espaços em branco
                $trimmedOutput = trim($output);
                if (!empty($trimmedOutput)) {
                    addWarning('Banco', 'Output detectado ao carregar db_connect.php (' . strlen($output) . ' bytes): ' . htmlspecialchars(substr($trimmedOutput, 0, 200)));
                    
                    // Verificar se o output parece ser código PHP
                    if (strpos($trimmedOutput, '<?php') !== false || strpos($trimmedOutput, 'if (file_exists') !== false) {
                        addError('Banco', 'Código PHP sendo exibido como texto! Verifique se há BOM (Byte Order Mark) ou espaços antes de <?php nos arquivos.');
                        addDebug('Banco', 'Primeiros 500 caracteres do output: ' . htmlspecialchars(substr($trimmedOutput, 0, 500)), 'error');
                    } else {
                        addDebug('Banco', 'Output completo (base64): ' . base64_encode($trimmedOutput), 'error');
                    }
                }
            }
            
            // Verificar se $pdo foi definido
            if (!isset($pdo)) {
                addError('Banco', 'Variável $pdo não foi definida após carregar db_connect.php');
                addDebug('Banco', 'Verificando variáveis: isset($pdo)=' . (isset($pdo) ? 'true' : 'false'), 'info');
            } elseif ($pdo === null) {
                // $pdo foi definido mas é null - houve erro
                if (isset($db_connect_error) && $db_connect_error) {
                    addError('Banco', 'Erro ao conectar: ' . $db_connect_error);
                    $dbError = $db_connect_error;
                    addDebug('Banco', 'Código do erro: ' . ($db_connect_error_code ?? 'N/A'), 'info');
                } else {
                    addError('Banco', 'Conexão retornou null mas nenhuma mensagem de erro foi definida');
                }
            } elseif ($pdo) {
            addSuccess('Banco', 'Conexão com banco de dados estabelecida via db_connect.php');
            
            // Testar query
            try {
                $testQuery = $pdo->query("SELECT 1");
                if ($testQuery) {
                    addSuccess('Banco', 'Query de teste executada com sucesso');
                }
            } catch (PDOException $e) {
                addError('Banco', 'Erro ao executar query de teste: ' . $e->getMessage());
            }
            
            // Verificar se as tabelas existem
            $requiredTables = ['google_oauth_tokens', 'google_integrations'];
            foreach ($requiredTables as $table) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        addSuccess('Banco', "Tabela '$table' existe");
                        
                        // Verificar estrutura
                        $stmt = $pdo->query("DESCRIBE $table");
                        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        addDebug('Banco', "Tabela '$table' tem " . count($columns) . " colunas", 'info');
                    } else {
                        addWarning('Banco', "Tabela '$table' não existe - será criada");
                    }
                } catch (PDOException $e) {
                    addError('Banco', "Erro ao verificar tabela '$table': " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        addError('Banco', 'Erro ao carregar db_connect.php: ' . $e->getMessage());
        addDebug('Banco', 'Stack trace: ' . $e->getTraceAsString(), 'error');
    } catch (Throwable $e) {
        addError('Banco', 'Erro fatal ao carregar db_connect.php: ' . $e->getMessage());
        addDebug('Banco', 'Stack trace: ' . $e->getTraceAsString(), 'error');
    }
} else {
    addError('Banco', 'Arquivo db_connect.php não encontrado em: ' . $dbConnectPath);
}

// 5. Tentar criar conexão direta para debug
if (!$pdo && !$dbError) {
    addWarning('Banco', 'Tentando criar conexão direta para debug...');
    try {
        $host = 'localhost';
        $dbname = 'u853242961_financeiro';
        $user = 'u853242961_user7';
        $pass = 'Lucastav8012@';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET time_zone = '-03:00'");
        addSuccess('Banco', 'Conexão direta estabelecida com sucesso!');
    } catch (PDOException $e) {
        addError('Banco', 'Erro ao conectar diretamente: ' . $e->getMessage() . ' (Código: ' . $e->getCode() . ')');
        
        // Análise do erro
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if (strpos($errorMessage, '2002') !== false || strpos($errorMessage, 'No such file') !== false) {
            addWarning('Banco', 'Possível causa: MySQL não está rodando ou host incorreto. Em produção, o host pode não ser "localhost"');
        } elseif (strpos($errorMessage, '1045') !== false || strpos($errorMessage, 'Access denied') !== false) {
            addWarning('Banco', 'Possível causa: Credenciais incorretas (usuário ou senha)');
        } elseif (strpos($errorMessage, '1049') !== false || strpos($errorMessage, 'Unknown database') !== false) {
            addWarning('Banco', 'Possível causa: Banco de dados não existe');
        } elseif (strpos($errorMessage, '2003') !== false || strpos($errorMessage, 'Can\'t connect') !== false) {
            addWarning('Banco', 'Possível causa: Não foi possível conectar ao servidor MySQL. Verifique se o MySQL está rodando.');
        }
    }
}

// 6. Verificar GoogleIntegrationManager
if (file_exists(__DIR__ . '/includes/google_integration_manager.php')) {
    try {
        require_once __DIR__ . '/includes/google_integration_manager.php';
        if ($pdo) {
            try {
                $manager = new GoogleIntegrationManager($pdo);
                addSuccess('Google Manager', 'GoogleIntegrationManager instanciado com sucesso');
                
                // Verificar se Client ID está configurado
                $reflection = new ReflectionClass($manager);
                $property = $reflection->getProperty('clientId');
                $property->setAccessible(true);
                $clientId = $property->getValue($manager);
                
                if (!empty($clientId)) {
                    addSuccess('Google Manager', 'Client ID está configurado no manager');
                } else {
                    addError('Google Manager', 'Client ID não está configurado no manager');
                }
            } catch (Exception $e) {
                addError('Google Manager', 'Erro ao instanciar GoogleIntegrationManager: ' . $e->getMessage());
            }
        } else {
            addWarning('Google Manager', 'Não foi possível testar GoogleIntegrationManager - conexão com banco não disponível');
        }
    } catch (Exception $e) {
        addError('Google Manager', 'Erro ao carregar GoogleIntegrationManager: ' . $e->getMessage());
    }
}

// 7. Tentar criar tabelas se possível
if ($pdo) {
    addDebug('Tabelas', 'Tentando criar tabelas...', 'info');
    
    $tablesToCreate = [
        'google_oauth_tokens' => "
            CREATE TABLE IF NOT EXISTS google_oauth_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT,
                token_type VARCHAR(50) DEFAULT 'Bearer',
                expires_in INT DEFAULT 3600,
                expires_at DATETIME,
                scope TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user (id_usuario),
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'google_integrations' => "
            CREATE TABLE IF NOT EXISTS google_integrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                service_name VARCHAR(50) NOT NULL,
                enabled TINYINT(1) DEFAULT 1,
                settings JSON,
                last_sync DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_service (id_usuario, service_name),
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];
    
    foreach ($tablesToCreate as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            addSuccess('Tabelas', "Tabela '$tableName' criada/verificada com sucesso");
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            addError('Tabelas', "Erro ao criar tabela '$tableName': $errorMsg");
            
            // Verificar se é erro de foreign key
            if (strpos($errorMsg, 'foreign key') !== false || strpos($errorMsg, 'REFERENCES') !== false) {
                addWarning('Tabelas', "Possível causa: Tabela 'usuarios' não existe ou estrutura incorreta");
            }
        }
    }
}

// 8. Verificar permissões de arquivos
$writableDirs = ['includes', 'cache'];
foreach ($writableDirs as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (is_dir($fullPath)) {
        if (is_writable($fullPath)) {
            addSuccess('Permissões', "Diretório '$dir' é gravável");
        } else {
            addWarning('Permissões', "Diretório '$dir' não é gravável");
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Integração Google</title>
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
                            <i class="bi bi-bug me-2"></i>Debug - Integração Google
                        </h2>
                        <small>Análise completa do sistema e correção automática de problemas</small>
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
                            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Ações Recomendadas</h5>
                            <ul class="mb-0">
                                <?php if (!$pdo): ?>
                                    <li><strong>Conexão com banco:</strong> Verifique as credenciais em <code>includes/db_connect.php</code></li>
                                    <li>Em produção, o host pode não ser <code>localhost</code> - verifique no painel da Hostinger</li>
                                <?php endif; ?>
                                <?php if (!file_exists($oauthConfigPath)): ?>
                                    <li><strong>Configuração OAuth:</strong> Crie o arquivo <code>includes/google_oauth_config.php</code> baseado em <code>includes/google_oauth_config.php.example</code></li>
                                <?php endif; ?>
                                <?php if ($pdo): ?>
                                    <li><a href="criar_tabelas_google_integration.php" class="btn btn-sm btn-primary mt-2">Criar Tabelas Agora</a></li>
                                <?php endif; ?>
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

                        <!-- Detalhes de Erros -->
                        <?php if (count($errors) > 0): ?>
                        <div class="mt-4">
                            <h5 class="text-danger"><i class="bi bi-x-circle me-2"></i>Erros Encontrados</h5>
                            <div class="list-group">
                                <?php foreach ($errors as $error): ?>
                                    <div class="list-group-item list-group-item-danger">
                                        <strong><?php echo htmlspecialchars($error['category']); ?>:</strong>
                                        <?php echo htmlspecialchars($error['message']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Avisos -->
                        <?php if (count($warnings) > 0): ?>
                        <div class="mt-4">
                            <h5 class="text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Avisos</h5>
                            <div class="list-group">
                                <?php foreach ($warnings as $warning): ?>
                                    <div class="list-group-item list-group-item-warning">
                                        <strong><?php echo htmlspecialchars($warning['category']); ?>:</strong>
                                        <?php echo htmlspecialchars($warning['message']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Informações do Sistema -->
                        <div class="mt-4">
                            <h5><i class="bi bi-info-circle me-2"></i>Informações do Sistema</h5>
                            <div class="code-block">
                                <div><strong>PHP Version:</strong> <?php echo phpversion(); ?></div>
                                <div><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></div>
                                <div><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></div>
                                <div><strong>Script Path:</strong> <?php echo __FILE__; ?></div>
                                <div><strong>Current Directory:</strong> <?php echo __DIR__; ?></div>
                                <div><strong>Extensions:</strong> 
                                    PDO: <?php echo extension_loaded('pdo') ? '✓' : '✗'; ?> | 
                                    PDO_MySQL: <?php echo extension_loaded('pdo_mysql') ? '✓' : '✗'; ?> |
                                    cURL: <?php echo extension_loaded('curl') ? '✓' : '✗'; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Botões de Ação -->
                        <div class="mt-4 d-flex gap-2">
                            <a href="integracoes_google.php" class="btn btn-primary">
                                <i class="bi bi-google me-2"></i>Ir para Integrações Google
                            </a>
                            <a href="criar_tabelas_google_integration.php" class="btn btn-success">
                                <i class="bi bi-database me-2"></i>Criar Tabelas
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
</body>
</html>
<?php
// Limpar output buffer e enviar
if (ob_get_level()) {
    ob_end_flush();
}
?>

