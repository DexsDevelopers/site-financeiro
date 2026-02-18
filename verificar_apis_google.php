<?php
// verificar_apis_google.php - Verificar status das APIs do Google

require_once 'templates/header.php';
require_once 'includes/google_integration_manager.php';

$manager = new GoogleIntegrationManager($pdo);
$isConnected = $manager->isConnected($userId);

$apis = [
    'tasks' => [
        'nome' => 'Google Tasks API',
        'url' => 'https://www.googleapis.com/tasks/v1/users/@me/lists',
        'ativacao' => 'https://console.developers.google.com/apis/api/tasks.googleapis.com/overview?project=945016861625'
    ],
    'calendar' => [
        'nome' => 'Google Calendar API',
        'url' => 'https://www.googleapis.com/calendar/v3/users/me/calendarList',
        'ativacao' => 'https://console.developers.google.com/apis/api/calendar-json.googleapis.com/overview?project=945016861625'
    ],
    'drive' => [
        'nome' => 'Google Drive API',
        'url' => 'https://www.googleapis.com/drive/v3/files',
        'ativacao' => 'https://console.developers.google.com/apis/api/drive.googleapis.com/overview?project=945016861625'
    ],
    'gmail' => [
        'nome' => 'Gmail API',
        'url' => 'https://www.googleapis.com/gmail/v1/users/me/profile',
        'ativacao' => 'https://console.developers.google.com/apis/api/gmail.googleapis.com/overview?project=945016861625',
        'scope_necessario' => 'https://www.googleapis.com/auth/gmail.readonly'
    ],
    'sheets' => [
        'nome' => 'Google Sheets API',
        'url' => 'https://www.googleapis.com/drive/v3/files',
        'ativacao' => 'https://console.developers.google.com/apis/api/sheets.googleapis.com/overview?project=945016861625'
    ]
];

$resultados = [];

if ($isConnected) {
    // Verificar scopes atuais do usuário
    $stmt = $pdo->prepare("SELECT scope FROM google_oauth_tokens WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $tokenInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $scopesAtuais = $tokenInfo ? explode(' ', $tokenInfo['scope']) : [];
    
    foreach ($apis as $key => $api) {
        try {
            // Verificar se o scope necessário está presente (para Gmail)
            if (isset($api['scope_necessario']) && !in_array($api['scope_necessario'], $scopesAtuais)) {
                $resultados[$key] = [
                    'status' => 'error',
                    'message' => 'Permissão OAuth insuficiente. Reconecte sua conta Google para obter as permissões necessárias.',
                    'habilitada' => true,
                    'tipo_erro' => 'scope_insuficiente'
                ];
                continue;
            }
            
            // Para cada API, fazer uma requisição de teste
            $response = $manager->makeApiRequest($userId, $api['url'], 'GET');
            $resultados[$key] = [
                'status' => 'success',
                'message' => 'API habilitada e funcionando',
                'habilitada' => true
            ];
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $errorData = json_decode($errorMessage, true);
            
            // Verificar se é erro de API não habilitada
            $isApiDisabled = false;
            $isAccessNotConfigured = false;
            $isInsufficientAuth = false;
            
            // Verificar se é erro 403 de autenticação insuficiente
            if (strpos($errorMessage, 'insufficient authentication') !== false || 
                strpos($errorMessage, 'insufficient authent') !== false ||
                (is_array($errorData) && isset($errorData['error']['code']) && $errorData['error']['code'] == 403)) {
                $isInsufficientAuth = true;
            }
            
            // Verificar no JSON de erro
            if (is_array($errorData)) {
                if (isset($errorData['error']['errors'])) {
                    foreach ($errorData['error']['errors'] as $error) {
                        if (isset($error['reason']) && $error['reason'] === 'accessNotConfigured') {
                            $isAccessNotConfigured = true;
                        }
                        if (isset($error['domain']) && $error['domain'] === 'googleapis.com' && 
                            isset($error['reason']) && $error['reason'] === 'SERVICE_DISABLED') {
                            $isApiDisabled = true;
                        }
                    }
                }
            }
            
            // Verificar na mensagem de texto
            if (strpos($errorMessage, 'SERVICE_DISABLED') !== false || 
                strpos($errorMessage, 'accessNotConfigured') !== false ||
                strpos($errorMessage, 'has not been used') !== false ||
                strpos($errorMessage, 'not been used in project') !== false ||
                strpos($errorMessage, 'it is disabled') !== false) {
                $isApiDisabled = true;
                $isAccessNotConfigured = true;
            }
            
            $tipoErro = 'outro';
            if ($isApiDisabled || $isAccessNotConfigured) {
                $tipoErro = 'api_nao_habilitada';
            } elseif ($isInsufficientAuth) {
                $tipoErro = 'scope_insuficiente';
            }
            
            $resultados[$key] = [
                'status' => 'error',
                'message' => $errorMessage,
                'habilitada' => !$isApiDisabled && !$isAccessNotConfigured,
                'tipo_erro' => $tipoErro
            ];
        }
    }
} else {
    $erroGeral = 'Conta Google não conectada. Conecte sua conta primeiro em <a href="integracoes_google.php">Integrações Google</a>.';
}
?>

<style>
    .api-status {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .api-status.success {
        background: rgba(40, 167, 69, 0.1);
        border-left: 4px solid #28a745;
    }
    .api-status.error {
        background: rgba(220, 53, 69, 0.1);
        border-left: 4px solid #dc3545;
    }
    .api-status.warning {
        background: rgba(255, 193, 7, 0.1);
        border-left: 4px solid #ffc107;
    }
    .btn-habilitar {
        margin-top: 0.5rem;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h1 class="h2 mb-1">
                <i class="bi bi-shield-check me-2 text-primary"></i>Verificar APIs do Google
            </h1>
            <p class="text-muted mb-0">
                Verifique quais APIs estão habilitadas e funcionando
            </p>
        </div>
        <div>
            <a href="integracoes_google.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Voltar
            </a>
        </div>
    </div>

    <?php if (isset($erroGeral)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo $erroGeral; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Dica:</strong> Se alguma API não estiver habilitada, clique no botão "Habilitar API" para abrir o Google Cloud Console.
        </div>

        <div class="row">
            <?php foreach ($apis as $key => $api): ?>
                <div class="col-12 col-md-6 col-lg-4 mb-3">
                    <div class="card card-custom">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-<?php 
                                    echo $key === 'tasks' ? 'check2-square' : 
                                        ($key === 'calendar' ? 'calendar-event' : 
                                        ($key === 'drive' ? 'cloud' : 
                                        ($key === 'gmail' ? 'envelope' : 'table'))); 
                                ?> me-2"></i>
                                <?php echo htmlspecialchars($api['nome']); ?>
                            </h5>
                            
                            <?php if (isset($resultados[$key])): ?>
                                <?php if ($resultados[$key]['status'] === 'success'): ?>
                                    <div class="api-status success">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        <strong>Status:</strong> <?php echo htmlspecialchars($resultados[$key]['message']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="api-status <?php echo $resultados[$key]['habilitada'] === false ? 'error' : 'warning'; ?>">
                                        <i class="bi <?php echo $resultados[$key]['habilitada'] === false ? 'bi-x-circle-fill text-danger' : 'bi-exclamation-triangle-fill text-warning'; ?> me-2"></i>
                                        <strong>Status:</strong> 
                                        <?php 
                                        if (isset($resultados[$key]['tipo_erro']) && $resultados[$key]['tipo_erro'] === 'scope_insuficiente') {
                                            echo 'Permissão OAuth insuficiente';
                                        } elseif ($resultados[$key]['habilitada'] === false) {
                                            echo 'API não habilitada';
                                        } else {
                                            echo 'Erro: ' . htmlspecialchars(substr($resultados[$key]['message'], 0, 100));
                                        }
                                        ?>
                                    </div>
                                    
                                    <?php if (isset($resultados[$key]['tipo_erro'])): ?>
                                        <?php if ($resultados[$key]['tipo_erro'] === 'api_nao_habilitada'): ?>
                                            <div class="mt-2">
                                                <a href="<?php echo htmlspecialchars($api['ativacao']); ?>" 
                                                   target="_blank" 
                                                   class="btn btn-primary btn-sm btn-habilitar w-100">
                                                    <i class="bi bi-power me-2"></i>Habilitar API no Google Cloud Console
                                                </a>
                                                <small class="text-muted d-block mt-2">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Clique no botão acima para abrir o Google Cloud Console e habilitar esta API.
                                                </small>
                                            </div>
                                        <?php elseif ($resultados[$key]['tipo_erro'] === 'scope_insuficiente'): ?>
                                            <div class="mt-2">
                                                <div class="alert alert-warning mb-2">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                                    <strong>Permissão OAuth insuficiente!</strong><br>
                                                    Você precisa reconectar sua conta Google para obter as permissões necessárias.
                                                </div>
                                                <a href="integracoes_google.php" class="btn btn-warning btn-sm w-100">
                                                    <i class="bi bi-arrow-clockwise me-2"></i>Reconectar Conta Google
                                                </a>
                                                <small class="text-muted d-block mt-2">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Desconecte e reconecte sua conta Google para obter as novas permissões.
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted d-block mt-2">
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                API habilitada, mas ocorreu um erro. Verifique as permissões OAuth.
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="api-status warning">
                                    <i class="bi bi-hourglass-split text-warning me-2"></i>
                                    <strong>Status:</strong> Não verificado
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card card-custom mt-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-book me-2"></i>Guia Completo
                </h5>
                <p class="card-text">
                    Para instruções detalhadas sobre como habilitar as APIs, consulte o arquivo:
                </p>
                <a href="GUIA_HABILITAR_APIS_GOOGLE.md" class="btn btn-outline-primary" target="_blank">
                    <i class="bi bi-file-text me-2"></i>Abrir Guia Completo
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php';
?>

