<?php
// integracoes_google.php - Página de Configuração de Integrações Google

require_once 'templates/header.php';
require_once 'includes/google_integration_manager.php';

$manager = new GoogleIntegrationManager($pdo);
$isConnected = $manager->isConnected($userId);

// Serviços disponíveis
$servicos = [
    'calendar' => [
        'nome' => 'Google Calendar',
        'icone' => 'bi-calendar-event',
        'descricao' => 'Sincronize seus eventos e compromissos',
        'cor' => '#4285F4'
    ],
    'drive' => [
        'nome' => 'Google Drive',
        'icone' => 'bi-cloud',
        'descricao' => 'Armazene e acesse arquivos na nuvem',
        'cor' => '#34A853'
    ],
    'tasks' => [
        'nome' => 'Google Tasks',
        'icone' => 'bi-check2-square',
        'descricao' => 'Sincronize suas tarefas',
        'cor' => '#FBBC04'
    ],
    'gmail' => [
        'nome' => 'Gmail',
        'icone' => 'bi-envelope',
        'descricao' => 'Envie emails diretamente do painel',
        'cor' => '#EA4335'
    ],
    'sheets' => [
        'nome' => 'Google Sheets',
        'icone' => 'bi-table',
        'descricao' => 'Exporte dados para planilhas',
        'cor' => '#0F9D58'
    ]
];

// Verificar status de cada serviço
foreach ($servicos as $key => &$servico) {
    $servico['enabled'] = $manager->isServiceEnabled($userId, $key);
}
unset($servico);
?>

<style>
    .service-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 2px solid var(--border-color, #333);
        cursor: pointer;
    }
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .service-card.enabled {
        border-color: #28a745;
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.05) 100%);
    }
    .service-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    .connection-status {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }
    .status-connected {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: #fff;
    }
    .status-disconnected {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: #fff;
    }
</style>

<div class="container-fluid py-4">
    <!-- Cabeçalho -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h2 mb-1">
                <i class="bi bi-google me-2 text-danger"></i>Integrações Google
            </h1>
            <p class="text-muted mb-0">
                Conecte seus apps do Google e tenha tudo sincronizado em um só lugar
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($isConnected): ?>
                <span class="connection-status status-connected">
                    <i class="bi bi-check-circle me-1"></i>Conectado
                </span>
                <a href="<?php echo htmlspecialchars($manager->getAuthUrl($userId)); ?>" class="btn btn-warning">
                    <i class="bi bi-arrow-clockwise me-2"></i>Reconectar (Atualizar Permissões)
                </a>
                <button class="btn btn-outline-danger" id="btn-desconectar-google">
                    <i class="bi bi-x-circle me-2"></i>Desconectar
                </button>
            <?php else: ?>
                <span class="connection-status status-disconnected">
                    <i class="bi bi-x-circle me-1"></i>Desconectado
                </span>
                <a href="<?php echo htmlspecialchars($manager->getAuthUrl($userId)); ?>" class="btn btn-danger">
                    <i class="bi bi-google me-2"></i>Conectar Google
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mensagens de Sucesso/Erro -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>Conta Google conectada com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Informações sobre Integração -->
    <?php if (!$isConnected): ?>
        <div class="card card-custom mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Como Funciona?
                </h5>
                <p class="card-text">
                    Ao conectar sua conta Google, você terá acesso a:
                </p>
                <ul>
                    <li><strong>Google Calendar:</strong> Sincronize eventos e compromissos automaticamente</li>
                    <li><strong>Google Drive:</strong> Armazene documentos e arquivos diretamente do painel</li>
                    <li><strong>Google Tasks:</strong> Sincronize suas tarefas entre o painel e o Google</li>
                    <li><strong>Gmail:</strong> Envie emails e notificações diretamente</li>
                    <li><strong>Google Sheets:</strong> Exporte relatórios e dados para planilhas</li>
                </ul>
                <p class="text-muted mb-0">
                    <small><i class="bi bi-shield-check me-1"></i>Seus dados são protegidos e você pode desconectar a qualquer momento.</small>
                </p>
            </div>
        </div>
        
        <?php
        // Verificar se Client Secret está configurado
        $clientSecretMissing = !defined('GOOGLE_CLIENT_SECRET') || (defined('GOOGLE_CLIENT_SECRET') && empty(GOOGLE_CLIENT_SECRET));
        if ($clientSecretMissing):
        ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Atenção:</strong> O Client Secret do Google OAuth não está configurado. 
            Consulte o arquivo <code>CONFIGURAR_GOOGLE_OAUTH.md</code> para instruções.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Cards de Serviços -->
    <div class="row g-4">
        <?php foreach ($servicos as $key => $servico): ?>
            <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up">
                <div class="card card-custom service-card <?php echo $servico['enabled'] ? 'enabled' : ''; ?>" 
                     data-service="<?php echo $key; ?>">
                    <div class="card-body text-center">
                        <div class="service-icon" style="color: <?php echo $servico['cor']; ?>;">
                            <i class="bi <?php echo $servico['icone']; ?>"></i>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($servico['nome']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($servico['descricao']); ?></p>
                        
                        <?php if ($isConnected): ?>
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input" type="checkbox" 
                                       id="toggle-<?php echo $key; ?>"
                                       data-service="<?php echo $key; ?>"
                                       <?php echo $servico['enabled'] ? 'checked' : ''; ?>
                                       style="transform: scale(1.3);">
                                <label class="form-check-label ms-2" for="toggle-<?php echo $key; ?>">
                                    <?php echo $servico['enabled'] ? 'Ativado' : 'Desativado'; ?>
                                </label>
                            </div>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="bi bi-lock me-1"></i>Conecte sua conta primeiro
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Seção de Verificação de APIs -->
    <?php if ($isConnected): ?>
        <div class="card card-custom mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-shield-check me-2"></i>Verificar APIs
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Verifique quais APIs do Google estão habilitadas e funcionando corretamente.
                </p>
                <a href="verificar_apis_google.php" class="btn btn-info">
                    <i class="bi bi-search me-2"></i>Verificar Status das APIs
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Seção de Sincronização -->
    <?php if ($isConnected): ?>
        <div class="card card-custom mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-arrow-repeat me-2"></i>Sincronização
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <button class="btn btn-primary w-100" id="btn-sync-calendar">
                            <i class="bi bi-calendar-event me-2"></i>Sincronizar Calendário
                        </button>
                    </div>
                    <div class="col-12 col-md-6">
                        <button class="btn btn-success w-100" id="btn-sync-tasks">
                            <i class="bi bi-check2-square me-2"></i>Sincronizar Tarefas
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        A sincronização automática ocorre a cada 15 minutos. Use os botões acima para sincronizar manualmente.
                    </small>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });

    // Toggle de serviços
    document.querySelectorAll('[data-service]').forEach(card => {
        const service = card.getAttribute('data-service');
        const toggle = card.querySelector('input[type="checkbox"]');
        
        if (toggle) {
            toggle.addEventListener('change', function() {
                const enabled = this.checked;
                
                fetch('toggle_google_service.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        service: service,
                        enabled: enabled
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        if (enabled) {
                            card.classList.add('enabled');
                        } else {
                            card.classList.remove('enabled');
                        }
                    } else {
                        showToast('Erro!', data.message, true);
                        this.checked = !enabled; // Reverter
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', true);
                    this.checked = !enabled; // Reverter
                });
            });
        }
    });

    // Desconectar Google
    const btnDesconectar = document.getElementById('btn-desconectar-google');
    if (btnDesconectar) {
        btnDesconectar.addEventListener('click', function() {
            if (!confirm('Tem certeza que deseja desconectar sua conta Google?\n\nTodas as integrações serão desativadas.')) {
                return;
            }
            
            fetch('desconectar_google.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
            });
        });
    }

    // Sincronizar Calendário
    const btnSyncCalendar = document.getElementById('btn-sync-calendar');
    if (btnSyncCalendar) {
        btnSyncCalendar.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sincronizando...';
            
            fetch('sync_google_calendar.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    }

    // Sincronizar Tarefas
    const btnSyncTasks = document.getElementById('btn-sync-tasks');
    if (btnSyncTasks) {
        btnSyncTasks.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sincronizando...';
            
            fetch('sync_google_tasks.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>

