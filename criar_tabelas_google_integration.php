<?php
// criar_tabelas_google_integration.php - Script para criar tabelas de integração Google

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}
// Iniciar output buffering para capturar erros
ob_start();

$pdo = null;
$dbError = null;

// Tentar carregar db_connect.php e capturar qualquer exceção
try {
    // Definir $pdo como null antes para garantir que existe
    $pdo = null;
    
    // Verificar se o arquivo existe
    $dbConnectPath = __DIR__ . '/includes/db_connect.php';
    if (!file_exists($dbConnectPath)) {
        $dbError = "Arquivo db_connect.php não encontrado em: " . $dbConnectPath;
    } else {
        // Carregar o arquivo - se houver erro, a exceção será lançada
        require_once $dbConnectPath;
        
        // Verificar se houve erro de conexão definido pelo db_connect.php
        if (isset($db_connect_error)) {
            $dbError = "Erro ao conectar: " . $db_connect_error . " (Código: " . ($db_connect_error_code ?? 'N/A') . ")";
            $pdo = null; // Garantir que $pdo é null se houver erro
        } elseif (!isset($pdo)) {
            // Se $pdo não foi definido e não há $db_connect_error, significa que o arquivo não executou corretamente
            $dbError = "Variável \$pdo não foi definida após carregar db_connect.php. O arquivo pode não ter sido executado corretamente.";
        } elseif (!$pdo) {
            $dbError = "Conexão com banco de dados retornou null";
        } else {
            // Testar a conexão fazendo uma query simples
            try {
                $testQuery = $pdo->query("SELECT 1");
                if (!$testQuery) {
                    $dbError = "Conexão estabelecida mas query de teste falhou";
                }
            } catch (PDOException $testE) {
                $dbError = "Erro ao testar conexão: " . $testE->getMessage();
            }
        }
    }
} catch (\PDOException $e) {
    $dbError = "Erro PDO ao conectar: " . $e->getMessage() . " (Código: " . $e->getCode() . ")";
    $pdo = null;
} catch (\Exception $e) {
    $dbError = "Erro ao carregar configuração: " . $e->getMessage() . " (Arquivo: " . $e->getFile() . ", Linha: " . $e->getLine() . ")";
    $pdo = null;
} catch (\Throwable $e) {
    $dbError = "Erro fatal: " . $e->getMessage() . " (Arquivo: " . $e->getFile() . ", Linha: " . $e->getLine() . ")";
    $pdo = null;
}

// Se ainda não temos $pdo e não temos erro, tentar criar a conexão diretamente
if (!isset($pdo) && !$dbError) {
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
        // Se chegou aqui, a conexão foi bem-sucedida
        $dbError = null;
    } catch (\PDOException $e) {
        $dbError = "Erro ao conectar diretamente: " . $e->getMessage() . " (Código: " . $e->getCode() . ")";
        $pdo = null;
    }
}

// Verificar se o arquivo de configuração existe e carregar novamente se necessário
$configPath = __DIR__ . '/includes/google_oauth_config.php';
if (file_exists($configPath) && (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET'))) {
    require_once $configPath;
}

// Verificar se as credenciais do Google OAuth estão configuradas
$hasClientId = defined('GOOGLE_CLIENT_ID');
$hasClientSecret = defined('GOOGLE_CLIENT_SECRET');
$clientIdValue = $hasClientId ? GOOGLE_CLIENT_ID : '';
$clientSecretValue = $hasClientSecret ? GOOGLE_CLIENT_SECRET : '';

$oauthConfigured = $hasClientId && $hasClientSecret && 
                  !empty($clientIdValue) && !empty($clientSecretValue);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Integração Google</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-success {
            background: #28a745;
            color: white;
        }
        .status-warning {
            background: #ffc107;
            color: #000;
        }
        .status-error {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0">
                            <i class="bi bi-google me-2"></i>Configurar Integração Google
                        </h2>
                    </div>
                    <div class="card-body">
                        <!-- Status das Credenciais -->
                        <div class="mb-4">
                            <h5>Status da Configuração</h5>
                            <div class="d-flex gap-3 align-items-center">
                                <span class="status-badge <?php echo $oauthConfigured ? 'status-success' : 'status-warning'; ?>">
                                    <i class="bi <?php echo $oauthConfigured ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-1"></i>
                                    Credenciais OAuth: <?php echo $oauthConfigured ? 'Configuradas' : 'Não Configuradas'; ?>
                                </span>
                                <?php if (!$oauthConfigured): ?>
                                    <small class="text-muted">
                                        Configure em: <code>includes/google_oauth_config.php</code>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr>

                        <h5 class="mb-3">🔗 Criando Tabelas de Integração Google</h5>

                        <?php
                        $tabelasCriadas = [];
                        $erros = [];
                        
                        // Verificar conexão com banco
                        if (!isset($pdo) || !$pdo || $dbError) {
                            echo "<div class='alert alert-danger'>";
                            echo "<h5 class='alert-heading'><i class='bi bi-exclamation-triangle me-2'></i>Erro de Conexão</h5>";
                            echo "<p class='mb-0'>Não foi possível conectar ao banco de dados. Verifique a configuração em <code>includes/db_connect.php</code>.</p>";
                            
                            // Mostrar detalhes do erro de forma mais destacada
                            if ($dbError) {
                                echo "<div class='mt-3 p-3 bg-dark text-white rounded'>";
                                echo "<strong><i class='bi bi-bug me-2'></i>Detalhes do Erro:</strong><br>";
                                echo "<code class='text-warning'>" . htmlspecialchars($dbError) . "</code>";
                                echo "</div>";
                            }
                            
                            // Debug: mostrar variáveis para diagnóstico
                            echo "<div class='mt-3 p-2 bg-secondary text-white rounded small'>";
                            echo "<strong>Debug:</strong><br>";
                            echo "\$pdo definido: " . (isset($pdo) ? 'Sim' : 'Não') . "<br>";
                            echo "\$dbError definido: " . (isset($dbError) && $dbError ? 'Sim' : 'Não') . "<br>";
                            if (isset($db_connect_error)) {
                                echo "\$db_connect_error: " . htmlspecialchars($db_connect_error) . "<br>";
                            }
                            echo "</div>";
                            
                            echo "<p class='mb-0 mt-3'><small><strong>Verifique se:</strong></small></p>";
                            echo "<ul class='mb-0'><small>";
                            echo "<li>O servidor MySQL está rodando</li>";
                            echo "<li>As credenciais em <code>includes/db_connect.php</code> estão corretas</li>";
                            echo "<li>O banco de dados <code>u853242961_financeiro</code> existe</li>";
                            echo "<li>O usuário tem permissões adequadas</li>";
                            echo "<li>O host está correto (pode não ser 'localhost' em produção)</li>";
                            echo "</small></ul>";
                            echo "</div>";
                        } else {
                            try {
                            // Verificar se tabela google_oauth_tokens existe
                            $stmt = $pdo->query("SHOW TABLES LIKE 'google_oauth_tokens'");
                            $tokensExiste = $stmt->rowCount() > 0;

                            // Tabela de tokens OAuth
                            try {
                                $pdo->exec("
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
                                ");
                                $tabelasCriadas[] = 'google_oauth_tokens';
                                echo "<div class='alert alert-success d-flex align-items-center'>";
                                echo "<i class='bi bi-check-circle-fill me-2 fs-5'></i>";
                                echo "<div>";
                                echo "<strong>Tabela 'google_oauth_tokens'</strong> ";
                                echo $tokensExiste ? "já existia e foi verificada" : "criada";
                                echo " com sucesso!";
                                echo "</div></div>";
                            } catch (PDOException $e) {
                                $erros[] = ['google_oauth_tokens', $e->getMessage()];
                                echo "<div class='alert alert-danger d-flex align-items-center'>";
                                echo "<i class='bi bi-x-circle-fill me-2 fs-5'></i>";
                                echo "<div><strong>Erro ao criar 'google_oauth_tokens':</strong> " . htmlspecialchars($e->getMessage()) . "</div></div>";
                            }
                            
                            // Verificar se tabela google_integrations existe
                            $stmt = $pdo->query("SHOW TABLES LIKE 'google_integrations'");
                            $integrationsExiste = $stmt->rowCount() > 0;

                            // Tabela de configurações de integração
                            try {
                                $pdo->exec("
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
                                ");
                                $tabelasCriadas[] = 'google_integrations';
                                echo "<div class='alert alert-success d-flex align-items-center'>";
                                echo "<i class='bi bi-check-circle-fill me-2 fs-5'></i>";
                                echo "<div>";
                                echo "<strong>Tabela 'google_integrations'</strong> ";
                                echo $integrationsExiste ? "já existia e foi verificada" : "criada";
                                echo " com sucesso!";
                                echo "</div></div>";
                            } catch (PDOException $e) {
                                $erros[] = ['google_integrations', $e->getMessage()];
                                echo "<div class='alert alert-danger d-flex align-items-center'>";
                                echo "<i class='bi bi-x-circle-fill me-2 fs-5'></i>";
                                echo "<div><strong>Erro ao criar 'google_integrations':</strong> " . htmlspecialchars($e->getMessage()) . "</div></div>";
                            }
                            
                            // Resumo
                            if (count($tabelasCriadas) === 2 && count($erros) === 0) {
                                echo "<div class='alert alert-success mt-3'>";
                                echo "<h5 class='alert-heading'><i class='bi bi-check-circle me-2'></i>Tudo Pronto!</h5>";
                                echo "<p class='mb-0'>Todas as tabelas foram criadas/verificadas com sucesso. A integração Google está pronta para uso!</p>";
                                echo "</div>";
                            }
                            
                            } catch (PDOException $e) {
                                echo "<div class='alert alert-danger'>";
                                echo "<h5 class='alert-heading'><i class='bi bi-exclamation-triangle me-2'></i>Erro de Conexão</h5>";
                                echo "<p class='mb-0'>Erro ao conectar ao banco de dados: " . htmlspecialchars($e->getMessage()) . "</p>";
                                echo "</div>";
                            }
                        }
                        ?>

                        <hr>

                        <div class="d-flex gap-2 justify-content-between">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
                            </a>
                            <a href="integracoes_google.php" class="btn btn-primary">
                                <i class="bi bi-google me-2"></i>Ir para Integrações Google
                            </a>
                        </div>

                        <?php if (!$oauthConfigured): ?>
                            <div class="alert alert-warning mt-3">
                                <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Próximo Passo</h6>
                                <p class="mb-0">
                                    Configure as credenciais do Google OAuth no arquivo 
                                    <code>includes/google_oauth_config.php</code> para habilitar a integração.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// Limpar output buffer e enviar
if (ob_get_level()) {
    ob_end_flush();
}
?>

