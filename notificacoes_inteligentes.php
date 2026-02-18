<?php
// notificacoes_inteligentes.php - Sistema de Notificações Inteligentes

require_once 'templates/header.php';

// Buscar notificações do usuário
$notificacoes = [];
$alertas = [];

try {
    // Notificações baseadas em padrões de gastos
    $stmt_padroes = $pdo->prepare("
        SELECT 
            c.nome as categoria,
            SUM(t.valor) as total_mes,
            COUNT(t.id) as quantidade,
            AVG(t.valor) as media_gasto
        FROM categorias c 
        JOIN transacoes t ON c.id = t.id_categoria 
        WHERE c.id_usuario = ? AND t.tipo = 'despesa' 
        AND MONTH(t.data_transacao) = ? AND YEAR(t.data_transacao) = ?
        GROUP BY c.id, c.nome
        HAVING total_mes > 500
        ORDER BY total_mes DESC
    ");
    $stmt_padroes->execute([$userId, date('n'), date('Y')]);
    $gastos_altos = $stmt_padroes->fetchAll();

    // Gerar notificações baseadas nos dados
    foreach ($gastos_altos as $gasto) {
        $notificacoes[] = [
            'tipo' => 'warning',
            'titulo' => 'Gasto Alto Detectado',
            'mensagem' => "Você gastou R$ " . number_format($gasto['total_mes'], 2, ',', '.') . " em {$gasto['categoria']} este mês.",
            'acao' => 'Ver Relatórios',
            'url' => 'relatorios.php',
            'data' => date('d/m/Y H:i')
        ];
    }

    // Verificar metas de compras (compatível com ambas as estruturas)
    try {
        // Primeiro tenta com valor_estimado
        $stmt_metas = $pdo->prepare("
            SELECT nome_item, valor_estimado as valor_meta, 
                   COALESCE(valor_poupado, 0) as valor_poupado
            FROM compras_futuras 
            WHERE id_usuario = ? AND status = 'planejando'
            ORDER BY valor_estimado ASC
        ");
        $stmt_metas->execute([$userId]);
        $metas_proximas = $stmt_metas->fetchAll();
    } catch (PDOException $e) {
        // Se falhar, tenta com valor_total
        try {
            $stmt_metas = $pdo->prepare("
                SELECT nome_item, valor_total as valor_meta, 
                       COALESCE(valor_poupado, 0) as valor_poupado
                FROM compras_futuras 
                WHERE id_usuario = ? AND status = 'planejando'
                ORDER BY valor_total ASC
            ");
            $stmt_metas->execute([$userId]);
            $metas_proximas = $stmt_metas->fetchAll();
        } catch (PDOException $e2) {
            $metas_proximas = [];
        }
    }

    foreach ($metas_proximas as $meta) {
        if (isset($meta['valor_meta']) && $meta['valor_meta'] > 0) {
            $percentual = ($meta['valor_poupado'] / $meta['valor_meta']) * 100;
            if ($percentual >= 80) {
                $notificacoes[] = [
                    'tipo' => 'success',
                    'titulo' => 'Meta Quase Alcançada!',
                    'mensagem' => "Você está " . number_format($percentual, 1) . "% próximo de conseguir {$meta['nome_item']}!",
                    'acao' => 'Ver Metas',
                    'url' => 'compras_futuras.php',
                    'data' => date('d/m/Y H:i')
                ];
            }
        }
    }

    // Verificar saldo negativo
    $stmt_saldo = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as receitas,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as despesas
        FROM transacoes 
        WHERE id_usuario = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?
    ");
    $stmt_saldo->execute([$userId, date('n'), date('Y')]);
    $saldo_data = $stmt_saldo->fetch(PDO::FETCH_ASSOC);
    
    $saldo_mes = ($saldo_data['receitas'] ?? 0) - ($saldo_data['despesas'] ?? 0);
    if ($saldo_mes < 0) {
        $notificacoes[] = [
            'tipo' => 'danger',
            'titulo' => 'Saldo Negativo',
            'mensagem' => "Seu saldo este mês está negativo em R$ " . number_format(abs($saldo_mes), 2, ',', '.') . ".",
            'acao' => 'Ver Dashboard',
            'url' => 'dashboard.php',
            'data' => date('d/m/Y H:i')
        ];
    }

    // Verificar gastos recorrentes
    $stmt_recorrentes = $pdo->prepare("
        SELECT 
            c.nome as categoria,
            COUNT(*) as frequencia,
            SUM(t.valor) as total
        FROM transacoes t
        JOIN categorias c ON t.id_categoria = c.id
        WHERE t.id_usuario = ? AND t.tipo = 'despesa'
        AND t.data_transacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY t.id_categoria, c.nome
        HAVING frequencia >= 5
        ORDER BY total DESC
    ");
    $stmt_recorrentes->execute([$userId]);
    $gastos_recorrentes = $stmt_recorrentes->fetchAll();

    foreach ($gastos_recorrentes as $recorrente) {
        $notificacoes[] = [
            'tipo' => 'info',
            'titulo' => 'Gasto Recorrente Detectado',
            'mensagem' => "Você tem gastado frequentemente em {$recorrente['categoria']} ({$recorrente['frequencia']} vezes no último mês).",
            'acao' => 'Ver Detalhes',
            'url' => 'relatorios.php',
            'data' => date('d/m/Y H:i')
        ];
    }

    // Verificar metas de economia
    $stmt_economia = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as receitas,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as despesas
        FROM transacoes 
        WHERE id_usuario = ? AND data_transacao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt_economia->execute([$userId]);
    $economia_semana = $stmt_economia->fetch(PDO::FETCH_ASSOC);
    
    $gasto_semanal = $economia_semana['despesas'] ?? 0;
    if ($gasto_semanal > 1000) {
        $notificacoes[] = [
            'tipo' => 'warning',
            'titulo' => 'Gasto Semanal Alto',
            'mensagem' => "Você gastou R$ " . number_format($gasto_semanal, 2, ',', '.') . " esta semana. Considere revisar seus gastos.",
            'acao' => 'Ver Relatórios',
            'url' => 'relatorios.php',
            'data' => date('d/m/Y H:i')
        ];
    }

} catch (PDOException $e) {
    // Em caso de erro, continuar sem notificações
    $notificacoes = [];
}

function getTipoIcon($tipo) {
    switch ($tipo) {
        case 'success': return 'bi-check-circle-fill';
        case 'warning': return 'bi-exclamation-triangle-fill';
        case 'danger': return 'bi-x-circle-fill';
        case 'info': return 'bi-info-circle-fill';
        default: return 'bi-bell-fill';
    }
}

function getTipoColor($tipo) {
    switch ($tipo) {
        case 'success': return 'text-success';
        case 'warning': return 'text-warning';
        case 'danger': return 'text-danger';
        case 'info': return 'text-info';
        default: return 'text-primary';
    }
}
?>

<style>
.notifications-header {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-300) 100%);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
}

.notification-card {
    background: var(--card-bg-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.notification-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--accent);
    transition: all 0.3s ease;
}

.notification-card.success::before {
    background: #28a745;
}

.notification-card.warning::before {
    background: #ffc107;
}

.notification-card.danger::before {
    background: #dc3545;
}

.notification-card.info::before {
    background: #17a2b8;
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.notification-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-right: 1rem;
    flex-shrink: 0;
}

.notification-icon.success {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.notification-icon.warning {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.notification-icon.danger {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

.notification-icon.info {
    background: rgba(23, 162, 184, 0.2);
    color: #17a2b8;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    color: var(--text-100);
    margin-bottom: 0.5rem;
}

.notification-message {
    color: var(--text-400);
    margin-bottom: 0.75rem;
    line-height: 1.5;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.75rem;
}

.notification-time {
    color: var(--text-600);
    font-size: 0.8rem;
}

.notification-actions {
    display: flex;
    gap: 0.5rem;
}

.settings-panel {
    background: var(--card-bg-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.settings-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}

.settings-item:last-child {
    border-bottom: none;
}

.settings-label {
    color: var(--text-100);
    font-weight: 500;
}

.settings-description {
    color: var(--text-400);
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-600);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--accent);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-400);
    font-size: 0.9rem;
}
</style>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="notifications-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-2">
                    <i class="bi bi-bell me-2"></i>Notificações Inteligentes
                </h1>
                <p class="mb-0 opacity-75">Fique por dentro de tudo que acontece com suas finanças</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <button class="btn btn-light" onclick="marcarTodasComoLidas()">
                        <i class="bi bi-check-all me-2"></i>Marcar Todas como Lidas
                    </button>
                    <button class="btn btn-outline-light" onclick="atualizarNotificacoes()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($notificacoes); ?></div>
            <div class="stat-label">Total de Notificações</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count(array_filter($notificacoes, fn($n) => $n['tipo'] === 'warning')); ?></div>
            <div class="stat-label">Alertas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count(array_filter($notificacoes, fn($n) => $n['tipo'] === 'success')); ?></div>
            <div class="stat-label">Sucessos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count(array_filter($notificacoes, fn($n) => $n['tipo'] === 'danger')); ?></div>
            <div class="stat-label">Urgentes</div>
        </div>
    </div>

    <!-- Notificações -->
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Suas Notificações</h4>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-light active" data-filter="todas">Todas</button>
                    <button class="btn btn-outline-light" data-filter="warning">Alertas</button>
                    <button class="btn btn-outline-light" data-filter="success">Sucessos</button>
                    <button class="btn btn-outline-light" data-filter="danger">Urgentes</button>
                </div>
            </div>

            <?php if (empty($notificacoes)): ?>
                <div class="empty-state">
                    <i class="bi bi-bell-slash"></i>
                    <h5>Nenhuma notificação</h5>
                    <p>Você está em dia! Não há notificações no momento.</p>
                </div>
            <?php else: ?>
                <div id="notificationsList">
                    <?php foreach ($notificacoes as $notificacao): ?>
                        <div class="notification-card <?php echo $notificacao['tipo']; ?>" data-type="<?php echo $notificacao['tipo']; ?>">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon <?php echo $notificacao['tipo']; ?>">
                                    <i class="bi <?php echo getTipoIcon($notificacao['tipo']); ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <h6 class="notification-title"><?php echo $notificacao['titulo']; ?></h6>
                                    <p class="notification-message"><?php echo $notificacao['mensagem']; ?></p>
                                    <div class="notification-meta">
                                        <span class="notification-time">
                                            <i class="bi bi-clock me-1"></i><?php echo $notificacao['data']; ?>
                                        </span>
                                        <div class="notification-actions">
                                            <button class="btn btn-sm btn-outline-primary" onclick="executarAcao('<?php echo $notificacao['url']; ?>')">
                                                <?php echo $notificacao['acao']; ?>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="marcarComoLida(this)">
                                                <i class="bi bi-check"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Configurações -->
            <div class="settings-panel">
                <h5 class="mb-3">
                    <i class="bi bi-gear me-2"></i>Configurações
                </h5>
                
                <div class="settings-item">
                    <div>
                        <div class="settings-label">Alertas de Gastos Altos</div>
                        <div class="settings-description">Notificar quando gastar muito em uma categoria</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertaGastos" checked>
                    </div>
                </div>

                <div class="settings-item">
                    <div>
                        <div class="settings-label">Progresso de Metas</div>
                        <div class="settings-description">Notificar sobre progresso das suas metas</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertaMetas" checked>
                    </div>
                </div>

                <div class="settings-item">
                    <div>
                        <div class="settings-label">Saldo Negativo</div>
                        <div class="settings-description">Alertar quando o saldo ficar negativo</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertaSaldo" checked>
                    </div>
                </div>

                <div class="settings-item">
                    <div>
                        <div class="settings-label">Gastos Recorrentes</div>
                        <div class="settings-description">Detectar padrões de gastos recorrentes</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="alertaRecorrentes" checked>
                    </div>
                </div>

                <div class="settings-item">
                    <div>
                        <div class="settings-label">Resumo Semanal</div>
                        <div class="settings-description">Receber resumo semanal por email</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="resumoSemanal">
                    </div>
                </div>

                <div class="d-grid mt-3">
                    <button class="btn btn-danger" onclick="salvarConfiguracoes()">
                        <i class="bi bi-save me-2"></i>Salvar Configurações
                    </button>
                </div>
            </div>

            <!-- Dicas -->
            <div class="settings-panel">
                <h5 class="mb-3">
                    <i class="bi bi-lightbulb me-2"></i>Dicas
                </h5>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Dica:</strong> Configure suas notificações para receber apenas os alertas mais importantes.
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Importante:</strong> As notificações são baseadas em padrões de seus gastos e podem ajudar a economizar.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Filtros de notificação
document.addEventListener('click', (e) => {
    if (e.target.matches('[data-filter]')) {
        const filter = e.target.dataset.filter;
        
        // Atualizar botões
        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.classList.remove('active');
        });
        e.target.classList.add('active');
        
        // Filtrar notificações
        const notifications = document.querySelectorAll('.notification-card');
        notifications.forEach(notification => {
            if (filter === 'todas' || notification.dataset.type === filter) {
                notification.style.display = 'block';
            } else {
                notification.style.display = 'none';
            }
        });
    }
});

function executarAcao(url) {
    window.location.href = url;
}

function marcarComoLida(button) {
    const notification = button.closest('.notification-card');
    notification.style.opacity = '0.5';
    notification.style.transform = 'scale(0.95)';
    
    setTimeout(() => {
        notification.remove();
        atualizarEstatisticas();
    }, 300);
}

function marcarTodasComoLidas() {
    const notifications = document.querySelectorAll('.notification-card');
    notifications.forEach((notification, index) => {
        setTimeout(() => {
            notification.style.opacity = '0.5';
            notification.style.transform = 'scale(0.95)';
            setTimeout(() => notification.remove(), 300);
        }, index * 100);
    });
    
    setTimeout(() => {
        atualizarEstatisticas();
    }, notifications.length * 100 + 500);
}

function atualizarNotificacoes() {
    window.location.reload();
}

function salvarConfiguracoes() {
    const configuracoes = {
        alertaGastos: document.getElementById('alertaGastos').checked,
        alertaMetas: document.getElementById('alertaMetas').checked,
        alertaSaldo: document.getElementById('alertaSaldo').checked,
        alertaRecorrentes: document.getElementById('alertaRecorrentes').checked,
        resumoSemanal: document.getElementById('resumoSemanal').checked
    };
    
    localStorage.setItem('notificacoes_config', JSON.stringify(configuracoes));
    
    // Mostrar feedback
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Sucesso!',
            text: 'Configurações salvas com sucesso',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    } else {
        alert('Configurações salvas com sucesso!');
    }
}

function atualizarEstatisticas() {
    const total = document.querySelectorAll('.notification-card').length;
    const alertas = document.querySelectorAll('.notification-card[data-type="warning"]').length;
    const sucessos = document.querySelectorAll('.notification-card[data-type="success"]').length;
    const urgentes = document.querySelectorAll('.notification-card[data-type="danger"]').length;
    
    // Atualizar valores (se os elementos existirem)
    const statCards = document.querySelectorAll('.stat-value');
    if (statCards.length >= 4) {
        statCards[0].textContent = total;
        statCards[1].textContent = alertas;
        statCards[2].textContent = sucessos;
        statCards[3].textContent = urgentes;
    }
}

// Carregar configurações salvas
document.addEventListener('DOMContentLoaded', () => {
    const config = localStorage.getItem('notificacoes_config');
    if (config) {
        const configuracoes = JSON.parse(config);
        document.getElementById('alertaGastos').checked = configuracoes.alertaGastos;
        document.getElementById('alertaMetas').checked = configuracoes.alertaMetas;
        document.getElementById('alertaSaldo').checked = configuracoes.alertaSaldo;
        document.getElementById('alertaRecorrentes').checked = configuracoes.alertaRecorrentes;
        document.getElementById('resumoSemanal').checked = configuracoes.resumoSemanal;
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
