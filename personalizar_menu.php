<?php
// personalizar_menu.php - Sistema de Personalização de Menu

require_once 'templates/header.php';
require_once 'includes/cache_manager.php';

// Buscar configurações do menu do usuário
$menu_config = $cache->getUserCache($userId, 'menu_personalizado');
if (!$menu_config) {
    // Configuração padrão com todas as seções e páginas
    $menu_config = [
        'secoes_visiveis' => [
            'academy' => true,
            'financeiro' => true,
            'produtividade' => true,
            'personalizacao' => true,
            'sistema' => true
        ],
        'paginas_visiveis' => [
            'academy' => ['cursos.php', 'treinos.php', 'rotina_academia.php', 'alimentacao.php', 'notas_cursos.php'],
            'financeiro' => ['compras_futuras.php', 'relatorios.php', 'extrato_completo.php', 'recorrentes.php', 'orcamento.php', 'categorias.php', 'regras_categorizacao.php', 'alertas_inteligentes.php'],
            'produtividade' => ['tarefas.php', 'calendario.php', 'temporizador.php'],
            'personalizacao' => ['temas_customizaveis.php', 'layouts_flexiveis.php', 'preferencias_avancadas.php', 'personalizar_menu.php'],
            'sistema' => ['perfil.php']
        ],
        'ordem_secoes' => ['academy', 'financeiro', 'produtividade', 'personalizacao', 'sistema'],
        'ordem_paginas' => [
            'academy' => ['cursos.php', 'treinos.php', 'rotina_academia.php', 'alimentacao.php', 'notas_cursos.php'],
            'financeiro' => ['compras_futuras.php', 'relatorios.php', 'extrato_completo.php', 'recorrentes.php', 'orcamento.php', 'categorias.php', 'regras_categorizacao.php', 'alertas_inteligentes.php'],
            'produtividade' => ['tarefas.php', 'calendario.php', 'temporizador.php'],
            'personalizacao' => ['temas_customizaveis.php', 'layouts_flexiveis.php', 'preferencias_avancadas.php', 'personalizar_menu.php'],
            'sistema' => ['perfil.php']
        ]
    ];
}

// Definir todas as seções e páginas disponíveis
$todas_secoes = [
    'academy' => [
        'nome' => 'Academy',
        'icone' => 'bi-mortarboard',
        'cor' => '#e50914',
        'paginas' => [
            'cursos.php' => ['nome' => 'Meus Cursos', 'icone' => 'bi-book'],
            'treinos.php' => ['nome' => 'Registro de Treinos', 'icone' => 'bi-dumbbell'],
            'rotina_academia.php' => ['nome' => 'Rotina', 'icone' => 'bi-calendar-check'],
            'alimentacao.php' => ['nome' => 'Alimentação', 'icone' => 'bi-apple'],
            'notas_cursos.php' => ['nome' => 'Notas e Anotações', 'icone' => 'bi-journal-text']
        ]
    ],
    'financeiro' => [
        'nome' => 'Financeiro',
        'icone' => 'bi-wallet2',
        'cor' => '#00b894',
        'paginas' => [
            'compras_futuras.php' => ['nome' => 'Metas de Compras', 'icone' => 'bi-bag-check'],
            'relatorios.php' => ['nome' => 'Relatórios', 'icone' => 'bi-graph-up'],
            'extrato_completo.php' => ['nome' => 'Extrato', 'icone' => 'bi-receipt'],
            'recorrentes.php' => ['nome' => 'Recorrentes', 'icone' => 'bi-arrow-repeat'],
            'orcamento.php' => ['nome' => 'Orçamentos', 'icone' => 'bi-calculator'],
            'categorias.php' => ['nome' => 'Categorias', 'icone' => 'bi-tags'],
            'regras_categorizacao.php' => ['nome' => 'Regras de Categorização', 'icone' => 'bi-robot'],
            'alertas_inteligentes.php' => ['nome' => 'Alertas Inteligentes', 'icone' => 'bi-bell-fill']
        ]
    ],
    'produtividade' => [
        'nome' => 'Produtividade',
        'icone' => 'bi-speedometer2',
        'cor' => '#0984e3',
        'paginas' => [
            'tarefas.php' => ['nome' => 'Rotina de Tarefas', 'icone' => 'bi-check2-square'],
            'calendario.php' => ['nome' => 'Calendário', 'icone' => 'bi-calendar3'],
            'temporizador.php' => ['nome' => 'Temporizador', 'icone' => 'bi-stopwatch']
        ]
    ],
    'personalizacao' => [
        'nome' => 'Personalização',
        'icone' => 'bi-gear',
        'cor' => '#f9a826',
        'paginas' => [
            'temas_customizaveis.php' => ['nome' => 'Temas Customizáveis', 'icone' => 'bi-palette'],
            'layouts_flexiveis.php' => ['nome' => 'Layouts Flexíveis', 'icone' => 'bi-layout-window'],
            'preferencias_avancadas.php' => ['nome' => 'Preferências Avançadas', 'icone' => 'bi-gear-fill'],
            'personalizar_menu.php' => ['nome' => 'Personalizar Menu', 'icone' => 'bi-list-ul']
        ]
    ],
    'sistema' => [
        'nome' => 'Sistema',
        'icone' => 'bi-shield-shaded',
        'cor' => '#6c757d',
        'paginas' => [
            'perfil.php' => ['nome' => 'Meu Perfil', 'icone' => 'bi-person-circle']
        ]
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
    .menu-customizer {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.5rem;
    }
    .secao-item {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    .secao-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .secao-header {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(229, 9, 20, 0.05) 100%);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 8px 8px 0 0;
        padding: 1rem;
        cursor: pointer;
    }
    .secao-content {
        padding: 1rem;
        display: none;
    }
    .secao-content.show {
        display: block;
    }
    .pagina-item {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        cursor: move;
        transition: all 0.3s ease;
    }
    .pagina-item:hover {
        background: rgba(229, 9, 20, 0.1);
        border-color: var(--accent-red);
    }
    .pagina-item.dragging {
        opacity: 0.5;
        transform: rotate(5deg);
    }
    .pagina-item.drag-over {
        border-color: var(--accent-red);
        background: rgba(229, 9, 20, 0.1);
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
    .preview-menu {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1rem;
        max-height: 400px;
        overflow-y: auto;
    }
    .preview-secao {
        margin-bottom: 1rem;
    }
    .preview-secao.hidden {
        display: none;
    }
    .preview-secao-header {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(229, 9, 20, 0.05) 100%);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 8px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .preview-pagina {
        padding: 0.5rem 1rem;
        border-left: 3px solid var(--accent-red);
        margin-bottom: 0.25rem;
        background: rgba(229, 9, 20, 0.05);
    }
    .preview-pagina.hidden {
        display: none;
    }
    .drag-handle {
        cursor: move;
        color: var(--text-secondary);
    }
    .drag-handle:hover {
        color: var(--accent-red);
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-list-ul display-1 text-danger mb-4"></i>
        <h1 class="display-5">Personalizar Menu</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Personalize completamente seu menu lateral. Escolha quais seções e páginas aparecem, organize a ordem e crie um menu único para suas necessidades.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Editor de Menu -->
    <div class="col-lg-8">
        <div class="menu-customizer">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Editor de Menu</h4>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" id="btn-expandir-todos">
                        <i class="bi bi-arrows-expand me-2"></i>Expandir Todos
                    </button>
                    <button class="btn btn-outline-secondary" id="btn-contrair-todos">
                        <i class="bi bi-arrows-collapse me-2"></i>Contrair Todos
                    </button>
                </div>
            </div>

            <div id="editor-menu">
                <?php foreach ($todas_secoes as $secao_key => $secao): ?>
                    <div class="secao-item" data-secao="<?php echo $secao_key; ?>">
                        <div class="secao-header" onclick="toggleSecao('<?php echo $secao_key; ?>')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $secao['icone']; ?> me-3" style="color: <?php echo $secao['cor']; ?>;"></i>
                                    <h5 class="mb-0"><?php echo $secao['nome']; ?></h5>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-secondary me-3">
                                        <?php echo count($secao['paginas']); ?> páginas
                                    </span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" 
                                               name="secao_<?php echo $secao_key; ?>" 
                                               <?php echo $menu_config['secoes_visiveis'][$secao_key] ? 'checked' : ''; ?>
                                               onchange="toggleSecaoVisibilidade('<?php echo $secao_key; ?>', this.checked)">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="secao-content" id="content_<?php echo $secao_key; ?>">
                            <div class="paginas-container" data-secao="<?php echo $secao_key; ?>">
                                <?php foreach ($secao['paginas'] as $pagina_key => $pagina): ?>
                                    <div class="pagina-item" 
                                         data-pagina="<?php echo $pagina_key; ?>" 
                                         data-secao="<?php echo $secao_key; ?>"
                                         draggable="true">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-grip-vertical drag-handle me-3"></i>
                                                <i class="bi <?php echo $pagina['icone']; ?> me-3"></i>
                                                <span><?php echo $pagina['nome']; ?></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" 
                                                           name="pagina_<?php echo $secao_key; ?>_<?php echo $pagina_key; ?>" 
                                                           <?php echo in_array($pagina_key, $menu_config['paginas_visiveis'][$secao_key]) ? 'checked' : ''; ?>
                                                           onchange="togglePaginaVisibilidade('<?php echo $secao_key; ?>', '<?php echo $pagina_key; ?>', this.checked)">
                                                    <span class="slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 text-center">
                <button class="btn btn-danger btn-lg" id="btn-salvar-menu">
                    <i class="bi bi-save me-2"></i>Salvar Configurações do Menu
                </button>
                <button class="btn btn-outline-secondary btn-lg ms-3" id="btn-resetar-menu">
                    <i class="bi bi-arrow-clockwise me-2"></i>Resetar para Padrão
                </button>
            </div>
        </div>
    </div>

    <!-- Preview do Menu -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Preview do Menu</h5>
            </div>
            <div class="card-body">
                <div class="preview-menu" id="preview-menu">
                    <!-- Preview será gerado dinamicamente -->
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">Estatísticas</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Seções Ativas:</span>
                    <span class="text-primary" id="stats-secoes">0</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Páginas Ativas:</span>
                    <span class="text-primary" id="stats-paginas">0</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Total de Páginas:</span>
                    <span class="text-muted"><?php echo array_sum(array_map('count', $todas_secoes)); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuração atual
    let menuConfig = <?php echo json_encode($menu_config); ?>;
    
    // Inicializar
    updatePreview();
    updateStats();
    
    // Toggle seção
    window.toggleSecao = function(secaoKey) {
        const content = document.getElementById('content_' + secaoKey);
        content.classList.toggle('show');
    };
    
    // Toggle visibilidade da seção
    window.toggleSecaoVisibilidade = function(secaoKey, visible) {
        menuConfig.secoes_visiveis[secaoKey] = visible;
        updatePreview();
        updateStats();
    };
    
    // Toggle visibilidade da página
    window.togglePaginaVisibilidade = function(secaoKey, paginaKey, visible) {
        if (visible) {
            if (!menuConfig.paginas_visiveis[secaoKey].includes(paginaKey)) {
                menuConfig.paginas_visiveis[secaoKey].push(paginaKey);
            }
        } else {
            const index = menuConfig.paginas_visiveis[secaoKey].indexOf(paginaKey);
            if (index > -1) {
                menuConfig.paginas_visiveis[secaoKey].splice(index, 1);
            }
        }
        updatePreview();
        updateStats();
    };
    
    // Expandir todos
    document.getElementById('btn-expandir-todos').addEventListener('click', function() {
        document.querySelectorAll('.secao-content').forEach(content => {
            content.classList.add('show');
        });
    });
    
    // Contrair todos
    document.getElementById('btn-contrair-todos').addEventListener('click', function() {
        document.querySelectorAll('.secao-content').forEach(content => {
            content.classList.remove('show');
        });
    });
    
    // Salvar configurações
    document.getElementById('btn-salvar-menu').addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';
        
        fetch('salvar_config_menu.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(menuConfig)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Configurações do menu salvas!');
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
    
    // Resetar menu
    document.getElementById('btn-resetar-menu').addEventListener('click', function() {
        if (confirm('Tem certeza que deseja resetar o menu para o padrão?')) {
            const button = this;
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Resetando...';
            
            fetch('resetar_config_menu.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reset: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Menu resetado!');
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
    
    // Drag and Drop para páginas
    document.querySelectorAll('.pagina-item').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            this.classList.add('dragging');
            e.dataTransfer.setData('text/plain', this.dataset.pagina);
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        item.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const draggedPagina = e.dataTransfer.getData('text/plain');
            const draggedElement = document.querySelector(`[data-pagina="${draggedPagina}"]`);
            const container = this.closest('.paginas-container');
            
            if (container && draggedElement) {
                container.insertBefore(draggedElement, this);
                updateOrdemPaginas();
            }
        });
    });
    
    // Atualizar preview
    function updatePreview() {
        const preview = document.getElementById('preview-menu');
        let html = '';
        
        Object.keys(menuConfig.ordem_secoes).forEach(secaoKey => {
            if (menuConfig.secoes_visiveis[secaoKey]) {
                const secao = <?php echo json_encode($todas_secoes); ?>[secaoKey];
                html += `<div class="preview-secao">`;
                html += `<div class="preview-secao-header">`;
                html += `<i class="bi ${secao.icone} me-2"></i>${secao.nome}`;
                html += `</div>`;
                
                menuConfig.paginas_visiveis[secaoKey].forEach(paginaKey => {
                    const pagina = secao.paginas[paginaKey];
                    html += `<div class="preview-pagina">`;
                    html += `<i class="bi ${pagina.icone} me-2"></i>${pagina.nome}`;
                    html += `</div>`;
                });
                
                html += `</div>`;
            }
        });
        
        preview.innerHTML = html;
    }
    
    // Atualizar estatísticas
    function updateStats() {
        const secoesAtivas = Object.values(menuConfig.secoes_visiveis).filter(v => v).length;
        const paginasAtivas = Object.values(menuConfig.paginas_visiveis).reduce((total, paginas) => total + paginas.length, 0);
        
        document.getElementById('stats-secoes').textContent = secoesAtivas;
        document.getElementById('stats-paginas').textContent = paginasAtivas;
    }
    
    // Atualizar ordem das páginas
    function updateOrdemPaginas() {
        document.querySelectorAll('.paginas-container').forEach(container => {
            const secaoKey = container.dataset.secao;
            const paginas = Array.from(container.querySelectorAll('.pagina-item')).map(item => item.dataset.pagina);
            menuConfig.paginas_visiveis[secaoKey] = paginas;
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
