<?php
// alertas_inteligentes.php - Sistema de Alertas Inteligentes

require_once 'templates/header.php';

// Buscar alertas existentes
$alertas = [];
$configuracoes = [];

// Limpar alertas de teste antigos automaticamente
try {
    $stmt_limpar = $pdo->prepare("DELETE FROM alertas_inteligentes WHERE id_usuario = ? AND (
        titulo LIKE '%Gasto Alto Detectado%' OR
        titulo LIKE '%Padrão de Gasto Identificado%' OR
        titulo LIKE '%Meta de Economia Atingida%' OR
        titulo LIKE '%Saldo Baixo%' OR
        mensagem LIKE '%R$ 1.200,00%' OR
        mensagem LIKE '%Supermercado Extra%' OR
        mensagem LIKE '%finais de semana%' OR
        mensagem LIKE '%meta de economia%' OR
        mensagem LIKE '%iPhone%' OR
        mensagem LIKE '%saldo atual%'
    )");
    $stmt_limpar->execute([$userId]);
} catch (PDOException $e) {
    // Ignorar erro se a tabela não existir
}

try {
    $stmt_alertas = $pdo->prepare("SELECT * FROM alertas_inteligentes WHERE id_usuario = ? ORDER BY data_criacao DESC LIMIT 50");
    $stmt_alertas->execute([$userId]);
    $alertas = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_config = $pdo->prepare("SELECT * FROM configuracoes_alertas WHERE id_usuario = ?");
    $stmt_config->execute([$userId]);
    $config_row = $stmt_config->fetch(PDO::FETCH_ASSOC);
    $configuracoes = $config_row ?: [
        'gasto_alto_valor' => 500,
        'gasto_semanal_limite' => 2000,
        'saldo_negativo_ativo' => 1,
        'metas_progresso_ativo' => 1,
        'gastos_recorrentes_ativo' => 1,
        'notificacoes_email' => 0,
        'notificacoes_push' => 1
    ];
} catch (PDOException $e) {
    // Se as tabelas não existirem, usar configurações padrão
    $configuracoes = [
        'gasto_alto_valor' => 500,
        'gasto_semanal_limite' => 2000,
        'saldo_negativo_ativo' => 1,
        'metas_progresso_ativo' => 1,
        'gastos_recorrentes_ativo' => 1,
        'notificacoes_email' => 0,
        'notificacoes_push' => 1
    ];
}
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
    .alert-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }
    .alert-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .alert-urgent {
        border-left: 4px solid #e50914;
    }
    .alert-warning {
        border-left: 4px solid #f9a826;
    }
    .alert-info {
        border-left: 4px solid #0984e3;
    }
    .alert-success {
        border-left: 4px solid #00b894;
    }
    .config-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: #e50914;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    .stats-card {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(229, 9, 20, 0.05) 100%);
        border: 1px solid rgba(229, 9, 20, 0.3);
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-bell-fill display-1 text-danger mb-4"></i>
        <h1 class="display-5">Alertas Inteligentes</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Sistema inteligente que monitora seus padrões financeiros e te alerta sobre situações importantes, gastos excessivos e oportunidades de economia.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Estatísticas -->
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-triangle feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count(array_filter($alertas, function($a) { return $a['tipo'] === 'urgente'; })); ?></h5>
                <p class="text-white-50 mb-0">Alertas Urgentes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-info-circle feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count(array_filter($alertas, function($a) { return $a['tipo'] === 'info'; })); ?></h5>
                <p class="text-white-50 mb-0">Informações</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-check-circle feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count(array_filter($alertas, function($a) { return $a['tipo'] === 'sucesso'; })); ?></h5>
                <p class="text-white-50 mb-0">Sucessos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-graph-up feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo count($alertas); ?></h5>
                <p class="text-white-50 mb-0">Total de Alertas</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Lista de Alertas -->
    <div class="col-lg-8">
        <div class="card alert-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Alertas Recentes</h4>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" id="btn-todos">Todos</button>
                    <button class="btn btn-outline-danger" id="btn-urgentes">Urgentes</button>
                    <button class="btn btn-outline-info" id="btn-info">Info</button>
                    <button class="btn btn-outline-success" id="btn-sucessos">Sucessos</button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($alertas)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum alerta ainda</h5>
                        <p class="text-muted">Configure os alertas abaixo para começar a receber notificações inteligentes.</p>
                    </div>
                <?php else: ?>
                    <div id="lista-alertas">
                        <?php foreach ($alertas as $alerta): ?>
                            <div class="alert alert-<?php echo $alerta['tipo']; ?> alert-dismissible fade show d-flex align-items-center" role="alert">
                                <i class="bi bi-<?php echo $alerta['tipo'] === 'urgente' ? 'exclamation-triangle' : ($alerta['tipo'] === 'info' ? 'info-circle' : 'check-circle'); ?> me-3"></i>
                                <div class="flex-grow-1">
                                    <h6 class="alert-heading mb-1"><?php echo htmlspecialchars($alerta['titulo']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($alerta['mensagem']); ?></p>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($alerta['data_criacao'])); ?></small>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Configurações -->
    <div class="col-lg-4">
        <div class="card config-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Configurações de Alertas</h5>
            </div>
            <div class="card-body">
                <form id="formConfiguracoes">
                    <div class="mb-4">
                        <h6 class="text-white-50 mb-3">Gastos e Limites</h6>
                        
                        <div class="mb-3">
                            <label for="gasto_alto_valor" class="form-label">Valor para Gasto Alto (R$)</label>
                            <input type="number" class="form-control" id="gasto_alto_valor" name="gasto_alto_valor" 
                                   value="<?php echo $configuracoes['gasto_alto_valor']; ?>" min="0" step="0.01">
                        </div>
                        
                        <div class="mb-3">
                            <label for="gasto_semanal_limite" class="form-label">Limite Semanal (R$)</label>
                            <input type="number" class="form-control" id="gasto_semanal_limite" name="gasto_semanal_limite" 
                                   value="<?php echo $configuracoes['gasto_semanal_limite']; ?>" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-white-50 mb-3">Tipos de Alertas</h6>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Saldo Negativo</span>
                            <label class="toggle-switch">
                                <input type="checkbox" name="saldo_negativo_ativo" <?php echo $configuracoes['saldo_negativo_ativo'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Progresso de Metas</span>
                            <label class="toggle-switch">
                                <input type="checkbox" name="metas_progresso_ativo" <?php echo $configuracoes['metas_progresso_ativo'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Gastos Recorrentes</span>
                            <label class="toggle-switch">
                                <input type="checkbox" name="gastos_recorrentes_ativo" <?php echo $configuracoes['gastos_recorrentes_ativo'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-white-50 mb-3">Notificações</h6>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Email</span>
                            <label class="toggle-switch">
                                <input type="checkbox" name="notificacoes_email" <?php echo $configuracoes['notificacoes_email'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Push (Navegador)</span>
                            <label class="toggle-switch">
                                <input type="checkbox" name="notificacoes_push" <?php echo $configuracoes['notificacoes_push'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-save me-2"></i>Salvar Configurações
                    </button>
                </form>
            </div>
        </div>

        <!-- Teste de Alertas -->
        <div class="card config-card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Teste de Alertas</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Teste o sistema de alertas com dados simulados.</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-danger" id="btn-teste-alertas">
                        <i class="bi bi-play-circle me-2"></i>Executar Teste
                    </button>
                    <button class="btn btn-outline-warning" id="btn-limpar-teste">
                        <i class="bi bi-trash me-2"></i>Limpar Alertas de Teste
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtros de alertas
    const btnTodos = document.getElementById('btn-todos');
    const btnUrgentes = document.getElementById('btn-urgentes');
    const btnInfo = document.getElementById('btn-info');
    const btnSucessos = document.getElementById('btn-sucessos');
    const listaAlertas = document.getElementById('lista-alertas');
    
    function filtrarAlertas(tipo) {
        const alertas = listaAlertas.querySelectorAll('.alert');
        alertas.forEach(alerta => {
            if (tipo === 'todos' || alerta.classList.contains(`alert-${tipo}`)) {
                alerta.style.display = 'flex';
            } else {
                alerta.style.display = 'none';
            }
        });
        
        // Atualizar botões ativos
        [btnTodos, btnUrgentes, btnInfo, btnSucessos].forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
    }
    
    btnTodos.addEventListener('click', () => filtrarAlertas('todos'));
    btnUrgentes.addEventListener('click', () => filtrarAlertas('urgente'));
    btnInfo.addEventListener('click', () => filtrarAlertas('info'));
    btnSucessos.addEventListener('click', () => filtrarAlertas('sucesso'));
    
    // Formulário de configurações
    const formConfig = document.getElementById('formConfiguracoes');
    formConfig.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const button = this.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
        
        fetch('salvar_configuracoes_alertas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Configurações salvas com sucesso!');
            } else {
                showToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
            showToast('Erro!', 'Erro de conexão', true);
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalText;
        });
    });
    
    // Teste de alertas
    const btnTeste = document.getElementById('btn-teste-alertas');
    btnTeste.addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testando...';
        
        fetch('testar_alertas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ teste: true })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Teste executado! Verifique os alertas.');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
            showToast('Erro!', 'Erro de conexão', true);
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalText;
        });
    });
    
    // Limpar alertas de teste
    const btnLimpar = document.getElementById('btn-limpar-teste');
    btnLimpar.addEventListener('click', function() {
        if (confirm('Tem certeza que deseja limpar todos os alertas de teste?')) {
            const button = this;
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Limpando...';
            
            fetch('limpar_alertas_teste.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ limpar: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => {
                showToast('Erro!', 'Erro de conexão', true);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
