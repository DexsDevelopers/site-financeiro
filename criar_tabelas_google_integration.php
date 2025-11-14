<?php
// criar_tabelas_google_integration.php - Script para criar tabelas de integração Google

require_once 'includes/db_connect.php';

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

