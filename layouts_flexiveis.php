<?php
// layouts_flexiveis.php - Sistema de Layouts Flexíveis

require_once 'templates/header.php';
require_once 'includes/cache_manager.php';

// Buscar configurações de layout do usuário
$layout_config = $cache->getUserCache($userId, 'layout_config');
if (!$layout_config) {
    $layout_config = [
        'tipo_layout' => 'padrao',
        'sidebar_posicao' => 'esquerda',
        'sidebar_tamanho' => 'normal',
        'header_fixo' => true,
        'footer_fixo' => false,
        'densidade' => 'normal',
        'animacoes' => true,
        'tema_escuro' => true
    ];
}

// Layouts disponíveis
$layouts_disponiveis = [
    'padrao' => [
        'nome' => 'Padrão',
        'descricao' => 'Layout clássico com sidebar e conteúdo principal',
        'icone' => 'bi-layout-sidebar'
    ],
    'compacto' => [
        'nome' => 'Compacto',
        'descricao' => 'Layout otimizado para telas menores',
        'icone' => 'bi-layout-text-sidebar-reverse'
    ],
    'minimalista' => [
        'nome' => 'Minimalista',
        'descricao' => 'Layout limpo com foco no conteúdo',
        'icone' => 'bi-layout-text-window'
    ],
    'dashboard' => [
        'nome' => 'Dashboard',
        'descricao' => 'Layout focado em métricas e gráficos',
        'icone' => 'bi-grid-3x3-gap'
    ],
    'mobile' => [
        'nome' => 'Mobile First',
        'descricao' => 'Layout otimizado para dispositivos móveis',
        'icone' => 'bi-phone'
    ]
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
    .layout-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .layout-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .layout-card.active {
        border-color: var(--accent-red);
        box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.3);
    }
    .layout-preview {
        height: 120px;
        border-radius: 8px;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
        background: var(--card-background);
        border: 1px solid var(--border-color);
    }
    .layout-preview::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--dark-bg);
    }
    .preview-sidebar {
        position: absolute;
        top: 10px;
        left: 10px;
        width: 30px;
        height: 100px;
        background: var(--accent-red);
        border-radius: 4px;
    }
    .preview-content {
        position: absolute;
        top: 10px;
        left: 50px;
        right: 10px;
        height: 100px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }
    .preview-header {
        position: absolute;
        top: 5px;
        left: 10px;
        right: 10px;
        height: 15px;
        background: var(--accent-red);
        border-radius: 2px;
    }
    .config-panel {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.5rem;
    }
    .preview-container {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
        min-height: 200px;
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
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-layout-window display-1 text-danger mb-4"></i>
        <h1 class="display-5">Layouts Flexíveis</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Personalize completamente o layout do seu painel. Escolha entre diferentes estilos de layout e configure cada detalhe para uma experiência única.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Layouts Disponíveis -->
    <div class="col-12">
        <div class="card layout-card">
            <div class="card-header">
                <h4 class="card-title mb-0">Estilos de Layout</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($layouts_disponiveis as $key => $layout): ?>
                        <div class="col-md-4 col-lg-2">
                            <div class="layout-card text-center <?php echo $layout_config['tipo_layout'] === $key ? 'active' : ''; ?>" 
                                 data-layout="<?php echo $key; ?>">
                                <div class="layout-preview">
                                    <div class="preview-sidebar"></div>
                                    <div class="preview-content"></div>
                                    <div class="preview-header"></div>
                                </div>
                                <h6 class="mb-1"><?php echo $layout['nome']; ?></h6>
                                <small class="text-muted"><?php echo $layout['descricao']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Configurações Avançadas -->
    <div class="col-lg-8">
        <div class="card layout-card">
            <div class="card-header">
                <h4 class="card-title mb-0">Configurações Avançadas</h4>
            </div>
            <div class="card-body">
                <form id="formLayoutConfig">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="sidebar_posicao" class="form-label">Posição da Sidebar</label>
                            <select class="form-select" id="sidebar_posicao" name="sidebar_posicao">
                                <option value="esquerda" <?php echo $layout_config['sidebar_posicao'] === 'esquerda' ? 'selected' : ''; ?>>Esquerda</option>
                                <option value="direita" <?php echo $layout_config['sidebar_posicao'] === 'direita' ? 'selected' : ''; ?>>Direita</option>
                                <option value="topo" <?php echo $layout_config['sidebar_posicao'] === 'topo' ? 'selected' : ''; ?>>Topo</option>
                                <option value="oculta" <?php echo $layout_config['sidebar_posicao'] === 'oculta' ? 'selected' : ''; ?>>Oculta</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="sidebar_tamanho" class="form-label">Tamanho da Sidebar</label>
                            <select class="form-select" id="sidebar_tamanho" name="sidebar_tamanho">
                                <option value="pequena" <?php echo $layout_config['sidebar_tamanho'] === 'pequena' ? 'selected' : ''; ?>>Pequena</option>
                                <option value="normal" <?php echo $layout_config['sidebar_tamanho'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="grande" <?php echo $layout_config['sidebar_tamanho'] === 'grande' ? 'selected' : ''; ?>>Grande</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="densidade" class="form-label">Densidade do Layout</label>
                            <select class="form-select" id="densidade" name="densidade">
                                <option value="compacta" <?php echo $layout_config['densidade'] === 'compacta' ? 'selected' : ''; ?>>Compacta</option>
                                <option value="normal" <?php echo $layout_config['densidade'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="confortavel" <?php echo $layout_config['densidade'] === 'confortavel' ? 'selected' : ''; ?>>Confortável</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tema_escuro" class="form-label">Tema Escuro</label>
                            <div class="d-flex align-items-center">
                                <label class="toggle-switch me-3">
                                    <input type="checkbox" name="tema_escuro" <?php echo $layout_config['tema_escuro'] ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span>Ativado</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-white-50 mb-3">Opções Avançadas</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Header Fixo</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="header_fixo" <?php echo $layout_config['header_fixo'] ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Footer Fixo</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="footer_fixo" <?php echo $layout_config['footer_fixo'] ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Animações</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="animacoes" <?php echo $layout_config['animacoes'] ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Modo Compacto</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="modo_compacto">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-save me-2"></i>Salvar Configurações
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="btnPreview">
                            <i class="bi bi-eye me-2"></i>Preview
                        </button>
                        <button type="button" class="btn btn-outline-warning ms-2" id="btnReset">
                            <i class="bi bi-arrow-clockwise me-2"></i>Resetar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview -->
    <div class="col-lg-4">
        <div class="card layout-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Preview do Layout</h5>
            </div>
            <div class="card-body">
                <div class="preview-container" id="previewContainer">
                    <div class="d-flex">
                        <div class="bg-primary rounded me-2" style="width: 20px; height: 20px;"></div>
                        <div class="flex-grow-1">
                            <div class="bg-secondary rounded mb-2" style="height: 10px;"></div>
                            <div class="bg-light rounded" style="height: 8px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="card layout-card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Estatísticas</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Layout Atual:</span>
                    <span class="text-primary"><?php echo $layouts_disponiveis[$layout_config['tipo_layout']]['nome']; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Sidebar:</span>
                    <span class="text-primary"><?php echo ucfirst($layout_config['sidebar_posicao']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Densidade:</span>
                    <span class="text-primary"><?php echo ucfirst($layout_config['densidade']); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Tema:</span>
                    <span class="text-primary"><?php echo $layout_config['tema_escuro'] ? 'Escuro' : 'Claro'; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Aplicar layout
    document.querySelectorAll('[data-layout]').forEach(card => {
        card.addEventListener('click', function() {
            const layoutKey = this.dataset.layout;
            
            // Remover active de todos
            document.querySelectorAll('[data-layout]').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            
            // Atualizar formulário
            document.getElementById('sidebar_posicao').value = 'esquerda';
            document.getElementById('sidebar_tamanho').value = 'normal';
            document.getElementById('densidade').value = 'normal';
            
            // Aplicar layout
            aplicarLayout(layoutKey);
        });
    });
    
    // Aplicar layout
    function aplicarLayout(layoutKey) {
        const body = document.body;
        
        // Remover classes de layout existentes
        body.classList.remove('layout-padrao', 'layout-compacto', 'layout-minimalista', 'layout-dashboard', 'layout-mobile');
        
        // Adicionar nova classe
        body.classList.add('layout-' + layoutKey);
        
        // Atualizar preview
        atualizarPreview();
    }
    
    // Atualizar preview
    function atualizarPreview() {
        const preview = document.getElementById('previewContainer');
        const sidebarPos = document.getElementById('sidebar_posicao').value;
        const sidebarSize = document.getElementById('sidebar_tamanho').value;
        
        // Resetar preview
        preview.innerHTML = '<div class="d-flex"><div class="bg-primary rounded me-2" style="width: 20px; height: 20px;"></div><div class="flex-grow-1"><div class="bg-secondary rounded mb-2" style="height: 10px;"></div><div class="bg-light rounded" style="height: 8px;"></div></div></div>';
        
        // Aplicar configurações
        if (sidebarPos === 'direita') {
            preview.querySelector('.d-flex').classList.add('flex-row-reverse');
        } else if (sidebarPos === 'topo') {
            preview.innerHTML = '<div><div class="bg-primary rounded mb-2" style="height: 15px;"></div><div class="bg-secondary rounded mb-2" style="height: 10px;"></div><div class="bg-light rounded" style="height: 8px;"></div></div>';
        }
        
        if (sidebarSize === 'pequena') {
            preview.querySelector('.bg-primary').style.width = '15px';
        } else if (sidebarSize === 'grande') {
            preview.querySelector('.bg-primary').style.width = '30px';
        }
    }
    
    // Preview em tempo real
    document.getElementById('btnPreview').addEventListener('click', function() {
        const formData = new FormData(document.getElementById('formLayoutConfig'));
        const config = Object.fromEntries(formData);
        
        // Aplicar configurações
        aplicarConfiguracoes(config);
        atualizarPreview();
    });
    
    // Aplicar configurações
    function aplicarConfiguracoes(config) {
        const body = document.body;
        
        // Densidade
        body.classList.remove('densidade-compacta', 'densidade-normal', 'densidade-confortavel');
        body.classList.add('densidade-' + config.densidade);
        
        // Tema
        if (config.tema_escuro) {
            body.classList.add('theme-dark');
        } else {
            body.classList.remove('theme-dark');
        }
        
        // Animações
        if (config.animacoes) {
            body.classList.add('animations-enabled');
        } else {
            body.classList.remove('animations-enabled');
        }
        
        // Salvar na sessão via AJAX
        fetch('aplicar_config_layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        });
    }
    
    // Formulário
    document.getElementById('formLayoutConfig').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const config = Object.fromEntries(formData);
        
        fetch('salvar_config_layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Configurações salvas!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erro!', data.message, true);
            }
        });
    });
    
    // Resetar
    document.getElementById('btnReset').addEventListener('click', function() {
        if (confirm('Tem certeza que deseja resetar todas as configurações?')) {
            fetch('resetar_config_layout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reset: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Configurações resetadas!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            });
        }
    });
    
    // Atualizar preview quando mudar configurações
    document.querySelectorAll('select, input[type="checkbox"]').forEach(element => {
        element.addEventListener('change', atualizarPreview);
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
