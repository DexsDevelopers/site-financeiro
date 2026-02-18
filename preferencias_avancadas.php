<?php
// preferencias_avancadas.php - Sistema de Preferências Avançadas

require_once 'templates/header.php';
require_once 'includes/cache_manager.php';

// Buscar preferências do usuário
$preferencias = $cache->getUserCache($userId, 'preferencias_avancadas');
if (!$preferencias) {
    $preferencias = [
        'notificacoes' => [
            'email' => true,
            'push' => true,
            'sms' => false,
            'som' => true,
            'vibrar' => false
        ],
        'interface' => [
            'idioma' => 'pt-BR',
            'fuso_horario' => 'America/Sao_Paulo',
            'formato_data' => 'dd/mm/yyyy',
            'formato_moeda' => 'BRL',
            'tamanho_fonte' => 'normal',
            'alto_contraste' => false,
            'reduzir_animacoes' => false
        ],
        'privacidade' => [
            'dados_analytics' => true,
            'cookies' => true,
            'localizacao' => false,
            'compartilhar_dados' => false,
            'backup_automatico' => true
        ],
        'performance' => [
            'cache_agressivo' => false,
            'lazy_loading' => true,
            'compressao_imagens' => true,
            'preload_recursos' => true,
            'otimizacao_mobile' => true
        ],
        'acessibilidade' => [
            'leitor_tela' => false,
            'navegacao_teclado' => true,
            'alto_contraste' => false,
            'fonte_grande' => false,
            'reduzir_movimento' => false
        ],
        'backup' => [
            'frequencia' => 'diario',
            'manter_historico' => 30,
            'comprimir_backup' => true,
            'notificar_backup' => true
        ]
    ];
}

// Idiomas disponíveis
$idiomas = [
    'pt-BR' => 'Português (Brasil)',
    'en-US' => 'English (US)',
    'es-ES' => 'Español',
    'fr-FR' => 'Français'
];

// Fusos horários
$fusos_horarios = [
    'America/Sao_Paulo' => 'São Paulo (UTC-3)',
    'America/New_York' => 'New York (UTC-5)',
    'Europe/London' => 'London (UTC+0)',
    'Asia/Tokyo' => 'Tokyo (UTC+9)',
    'Australia/Sydney' => 'Sydney (UTC+10)'
];
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
    .preference-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }
    .preference-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
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
        border-radius: 24px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: var(--accent-red);
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    .preference-item {
        display: flex;
        justify-content: between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border-color);
    }
    .preference-item:last-child {
        border-bottom: none;
    }
    .preference-info {
        flex-grow: 1;
    }
    .preference-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .preference-desc {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    .preference-control {
        margin-left: 1rem;
    }
    .section-header {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(229, 9, 20, 0.05) 100%);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-gear-fill display-1 text-danger mb-4"></i>
        <h1 class="display-5">Preferências Avançadas</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Configure cada detalhe do seu painel para uma experiência personalizada. Ajuste notificações, interface, privacidade e muito mais.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Notificações -->
    <div class="col-lg-6">
        <div class="card preference-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bell me-2"></i>Notificações
                </h5>
            </div>
            <div class="card-body">
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Email</div>
                        <div class="preference-desc">Receber notificações por email</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="notificacoes[email]" <?php echo $preferencias['notificacoes']['email'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Push (Navegador)</div>
                        <div class="preference-desc">Notificações push no navegador</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="notificacoes[push]" <?php echo $preferencias['notificacoes']['push'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">SMS</div>
                        <div class="preference-desc">Notificações por SMS</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="notificacoes[sms]" <?php echo $preferencias['notificacoes']['sms'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Som</div>
                        <div class="preference-desc">Reproduzir som nas notificações</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="notificacoes[som]" <?php echo $preferencias['notificacoes']['som'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Vibração</div>
                        <div class="preference-desc">Vibrar em dispositivos móveis</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="notificacoes[vibrar]" <?php echo $preferencias['notificacoes']['vibrar'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interface -->
    <div class="col-lg-6">
        <div class="card preference-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-palette me-2"></i>Interface
                </h5>
            </div>
            <div class="card-body">
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Idioma</div>
                        <div class="preference-desc">Idioma da interface</div>
                    </div>
                    <div class="preference-control">
                        <select class="form-select form-select-sm" name="interface[idioma]" style="width: 150px;">
                            <?php foreach ($idiomas as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo $preferencias['interface']['idioma'] === $code ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Fuso Horário</div>
                        <div class="preference-desc">Fuso horário para exibição de datas</div>
                    </div>
                    <div class="preference-control">
                        <select class="form-select form-select-sm" name="interface[fuso_horario]" style="width: 200px;">
                            <?php foreach ($fusos_horarios as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo $preferencias['interface']['fuso_horario'] === $code ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Formato de Data</div>
                        <div class="preference-desc">Como exibir datas</div>
                    </div>
                    <div class="preference-control">
                        <select class="form-select form-select-sm" name="interface[formato_data]" style="width: 120px;">
                            <option value="dd/mm/yyyy" <?php echo $preferencias['interface']['formato_data'] === 'dd/mm/yyyy' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                            <option value="mm/dd/yyyy" <?php echo $preferencias['interface']['formato_data'] === 'mm/dd/yyyy' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                            <option value="yyyy-mm-dd" <?php echo $preferencias['interface']['formato_data'] === 'yyyy-mm-dd' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                        </select>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Tamanho da Fonte</div>
                        <div class="preference-desc">Tamanho da fonte da interface</div>
                    </div>
                    <div class="preference-control">
                        <select class="form-select form-select-sm" name="interface[tamanho_fonte]" style="width: 120px;">
                            <option value="pequena" <?php echo $preferencias['interface']['tamanho_fonte'] === 'pequena' ? 'selected' : ''; ?>>Pequena</option>
                            <option value="normal" <?php echo $preferencias['interface']['tamanho_fonte'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="grande" <?php echo $preferencias['interface']['tamanho_fonte'] === 'grande' ? 'selected' : ''; ?>>Grande</option>
                        </select>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Alto Contraste</div>
                        <div class="preference-desc">Modo de alto contraste</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="interface[alto_contraste]" <?php echo $preferencias['interface']['alto_contraste'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacidade -->
    <div class="col-lg-6">
        <div class="card preference-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-shield-lock me-2"></i>Privacidade
                </h5>
            </div>
            <div class="card-body">
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Dados de Analytics</div>
                        <div class="preference-desc">Compartilhar dados para melhorias</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="privacidade[dados_analytics]" <?php echo $preferencias['privacidade']['dados_analytics'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Cookies</div>
                        <div class="preference-desc">Aceitar cookies</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="privacidade[cookies]" <?php echo $preferencias['privacidade']['cookies'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Localização</div>
                        <div class="preference-desc">Compartilhar localização</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="privacidade[localizacao]" <?php echo $preferencias['privacidade']['localizacao'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Backup Automático</div>
                        <div class="preference-desc">Fazer backup automático dos dados</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="privacidade[backup_automatico]" <?php echo $preferencias['privacidade']['backup_automatico'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance -->
    <div class="col-lg-6">
        <div class="card preference-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-speedometer2 me-2"></i>Performance
                </h5>
            </div>
            <div class="card-body">
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Cache Agressivo</div>
                        <div class="preference-desc">Usar cache mais agressivo</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="performance[cache_agressivo]" <?php echo $preferencias['performance']['cache_agressivo'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Lazy Loading</div>
                        <div class="preference-desc">Carregar conteúdo sob demanda</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="performance[lazy_loading]" <?php echo $preferencias['performance']['lazy_loading'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Compressão de Imagens</div>
                        <div class="preference-desc">Comprimir imagens automaticamente</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="performance[compressao_imagens]" <?php echo $preferencias['performance']['compressao_imagens'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Preload de Recursos</div>
                        <div class="preference-desc">Pré-carregar recursos importantes</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="performance[preload_recursos]" <?php echo $preferencias['performance']['preload_recursos'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-title">Otimização Mobile</div>
                        <div class="preference-desc">Otimizar para dispositivos móveis</div>
                    </div>
                    <div class="preference-control">
                        <label class="toggle-switch">
                            <input type="checkbox" name="performance[otimizacao_mobile]" <?php echo $preferencias['performance']['otimizacao_mobile'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup -->
    <div class="col-12">
        <div class="card preference-card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cloud-arrow-up me-2"></i>Backup e Segurança
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Frequência do Backup</label>
                        <select class="form-select" name="backup[frequencia]">
                            <option value="diario" <?php echo $preferencias['backup']['frequencia'] === 'diario' ? 'selected' : ''; ?>>Diário</option>
                            <option value="semanal" <?php echo $preferencias['backup']['frequencia'] === 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                            <option value="mensal" <?php echo $preferencias['backup']['frequencia'] === 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Manter Histórico (dias)</label>
                        <input type="number" class="form-control" name="backup[manter_historico]" 
                               value="<?php echo $preferencias['backup']['manter_historico']; ?>" min="1" max="365">
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mt-4">
                            <label class="toggle-switch me-3">
                                <input type="checkbox" name="backup[comprimir_backup]" <?php echo $preferencias['backup']['comprimir_backup'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span>Comprimir Backup</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botões de Ação -->
    <div class="col-12">
        <div class="card preference-card">
            <div class="card-body text-center">
                <button type="button" class="btn btn-danger btn-lg me-3" id="btnSalvar">
                    <i class="bi bi-save me-2"></i>Salvar Preferências
                </button>
                <button type="button" class="btn btn-outline-secondary btn-lg me-3" id="btnReset">
                    <i class="bi bi-arrow-clockwise me-2"></i>Resetar
                </button>
                <button type="button" class="btn btn-outline-info btn-lg" id="btnExport">
                    <i class="bi bi-download me-2"></i>Exportar Configurações
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Salvar preferências
    document.getElementById('btnSalvar').addEventListener('click', function() {
        const formData = new FormData();
        
        // Coletar todos os dados do formulário
        document.querySelectorAll('input, select').forEach(element => {
            if (element.name) {
                if (element.type === 'checkbox') {
                    formData.append(element.name, element.checked ? '1' : '0');
                } else {
                    formData.append(element.name, element.value);
                }
            }
        });
        
        fetch('salvar_preferencias_avancadas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Preferências salvas com sucesso!');
            } else {
                showToast('Erro!', data.message, true);
            }
        });
    });
    
    // Resetar preferências
    document.getElementById('btnReset').addEventListener('click', function() {
        if (confirm('Tem certeza que deseja resetar todas as preferências?')) {
            fetch('resetar_preferencias_avancadas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reset: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Preferências resetadas!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            });
        }
    });
    
    // Exportar configurações
    document.getElementById('btnExport').addEventListener('click', function() {
        const preferencias = {};
        
        document.querySelectorAll('input, select').forEach(element => {
            if (element.name) {
                const keys = element.name.split('[');
                let current = preferencias;
                
                for (let i = 0; i < keys.length; i++) {
                    const key = keys[i].replace(']', '');
                    if (i === keys.length - 1) {
                        current[key] = element.type === 'checkbox' ? element.checked : element.value;
                    } else {
                        if (!current[key]) current[key] = {};
                        current = current[key];
                    }
                }
            }
        });
        
        const dataStr = JSON.stringify(preferencias, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        const url = URL.createObjectURL(dataBlob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = 'preferencias_config.json';
        link.click();
        
        URL.revokeObjectURL(url);
        showToast('Sucesso!', 'Configurações exportadas!');
    });
    
    // Aplicar mudanças em tempo real
    document.querySelectorAll('input[type="checkbox"], select').forEach(element => {
        element.addEventListener('change', function() {
            // Aplicar mudanças específicas
            if (this.name === 'interface[alto_contraste]') {
                document.body.classList.toggle('high-contrast', this.checked);
            }
            
            if (this.name === 'interface[tamanho_fonte]') {
                document.body.className = document.body.className.replace(/font-\w+/g, '');
                document.body.classList.add('font-' + this.value);
            }
            
            // Salvar mudanças na sessão via AJAX
            const formData = new FormData();
            document.querySelectorAll('input, select').forEach(el => {
                if (el.name) {
                    if (el.type === 'checkbox') {
                        formData.append(el.name, el.checked ? '1' : '0');
                    } else {
                        formData.append(el.name, el.value);
                    }
                }
            });
            
            fetch('aplicar_preferencias_avancadas.php', {
                method: 'POST',
                body: formData
            });
        });
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
