<?php
// gerenciar_sessoes.php - Gerenciar sessões e tokens de lembrança

require_once 'templates/header.php';
require_once 'includes/remember_me_manager.php';

$rememberManager = new RememberMeManager($pdo);

// Buscar tokens ativos do usuário
$tokens = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM remember_tokens 
        WHERE user_id = ? 
        AND is_active = 1 
        ORDER BY last_used_at DESC
    ");
    $stmt->execute([$userId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Erro ao buscar tokens
}

// Buscar estatísticas
$stats = $rememberManager->getTokenStats($userId);
?>

<style>
    .intro-card {
        background: linear-gradient(135deg, rgba(30, 30, 30, 0.5) 0%, rgba(30, 30, 50, 0.5) 100%);
    }
    .intro-card h1 {
        font-weight: 700;
    }
    .feature-icon {
        font-size: 2.5rem;
        color: var(--accent-red);
    }
    .session-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }
    .session-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .session-active {
        border-left: 4px solid #00b894;
    }
    .session-expired {
        border-left: 4px solid #e50914;
    }
    .device-info {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-shield-lock display-1 text-danger mb-4"></i>
        <h1 class="display-5">Gerenciar Sessões</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Gerencie suas sessões ativas e dispositivos conectados. Revogue acesso de dispositivos não autorizados e mantenha sua conta segura.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Estatísticas -->
    <div class="col-md-3">
        <div class="card session-card">
            <div class="card-body text-center">
                <i class="bi bi-check-circle feature-icon mb-3" style="color: #00b894;"></i>
                <h5 class="card-title"><?php echo $stats['active_tokens'] ?? 0; ?></h5>
                <p class="text-white-50 mb-0">Sessões Ativas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card session-card">
            <div class="card-body text-center">
                <i class="bi bi-x-circle feature-icon mb-3" style="color: #e50914;"></i>
                <h5 class="card-title"><?php echo $stats['revoked_tokens'] ?? 0; ?></h5>
                <p class="text-white-50 mb-0">Sessões Revogadas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card session-card">
            <div class="card-body text-center">
                <i class="bi bi-clock feature-icon mb-3" style="color: #f9a826;"></i>
                <h5 class="card-title"><?php echo $stats['expired_tokens'] ?? 0; ?></h5>
                <p class="text-white-50 mb-0">Sessões Expiradas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card session-card">
            <div class="card-body text-center">
                <i class="bi bi-graph-up feature-icon mb-3" style="color: #0984e3;"></i>
                <h5 class="card-title"><?php echo $stats['total_tokens'] ?? 0; ?></h5>
                <p class="text-white-50 mb-0">Total de Sessões</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Lista de Sessões -->
    <div class="col-lg-8">
        <div class="card session-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Sessões Ativas</h4>
                <button class="btn btn-outline-danger btn-sm" id="btn-revocar-todas">
                    <i class="bi bi-trash me-2"></i>Revogar Todas
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($tokens)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shield-check display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhuma sessão ativa</h5>
                        <p class="text-muted">Você não possui sessões de "Lembrar-me" ativas no momento.</p>
                    </div>
                <?php else: ?>
                    <div id="lista-sessoes">
                        <?php foreach ($tokens as $token): ?>
                            <div class="session-item session-card mb-3 p-3" data-token="<?php echo $token['token']; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-laptop me-2"></i>
                                            <h6 class="mb-0">Dispositivo Conectado</h6>
                                            <span class="badge bg-success status-badge ms-2">Ativo</span>
                                        </div>
                                        
                                        <div class="device-info mb-2">
                                            <div><strong>IP:</strong> <?php echo htmlspecialchars($token['ip_address']); ?></div>
                                            <div><strong>Último uso:</strong> <?php echo date('d/m/Y H:i', strtotime($token['last_used_at'])); ?></div>
                                            <div><strong>Expira em:</strong> <?php echo date('d/m/Y H:i', strtotime($token['expires_at'])); ?></div>
                                        </div>
                                        
                                        <?php if ($token['user_agent']): ?>
                                            <div class="device-info">
                                                <small><?php echo htmlspecialchars(substr($token['user_agent'], 0, 100)); ?>...</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="ms-3">
                                        <button class="btn btn-outline-danger btn-sm btn-revocar-sessao" 
                                                data-token="<?php echo $token['token']; ?>"
                                                title="Revogar esta sessão">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Informações de Segurança -->
    <div class="col-lg-4">
        <div class="card session-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Dicas de Segurança</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Dica:</strong> Revogue sessões de dispositivos que você não reconhece.
                </div>
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Atenção:</strong> Tokens expiram automaticamente em 30 dias.
                </div>
                
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Seguro:</strong> Tokens são criptografados e seguros.
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="card session-card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Ações Rápidas</h6>
            </div>
            <div class="card-body">
                <button class="btn btn-outline-primary w-100 mb-2" id="btn-limpar-expirados">
                    <i class="bi bi-trash me-2"></i>Limpar Expirados
                </button>
                <button class="btn btn-outline-warning w-100" id="btn-renovar-todos">
                    <i class="bi bi-arrow-clockwise me-2"></i>Renovar Todos
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revogar sessão individual
    document.querySelectorAll('.btn-revocar-sessao').forEach(btn => {
        btn.addEventListener('click', function() {
            const token = this.dataset.token;
            
            if (confirm('Tem certeza que deseja revogar esta sessão?')) {
                fetch('revogar_sessao.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: token })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', 'Sessão revogada!');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, true);
                    }
                });
            }
        });
    });
    
    // Revogar todas as sessões
    document.getElementById('btn-revocar-todas').addEventListener('click', function() {
        if (confirm('Tem certeza que deseja revogar TODAS as sessões? Isso fará logout de todos os dispositivos.')) {
            fetch('revogar_todas_sessoes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ revogar_todas: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Todas as sessões foram revogadas!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            });
        }
    });
    
    // Limpar tokens expirados
    document.getElementById('btn-limpar-expirados').addEventListener('click', function() {
        fetch('limpar_tokens_expirados.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ limpar: true })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erro!', data.message, true);
            }
        });
    });
    
    // Renovar todos os tokens
    document.getElementById('btn-renovar-todos').addEventListener('click', function() {
        if (confirm('Tem certeza que deseja renovar todos os tokens? Isso pode causar logout em alguns dispositivos.')) {
            fetch('renovar_todos_tokens.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ renovar: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            });
        }
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
