<?php
// temas_customizaveis.php - Sistema de Temas Customizáveis

require_once 'templates/header.php';
require_once 'includes/cache_manager.php';

// Buscar temas existentes
$temas = [];
$tema_ativo = $_SESSION['tema_ativo'] ?? 'padrao';

try {
    $stmt = $pdo->prepare("SELECT * FROM temas_personalizados WHERE id_usuario = ? ORDER BY nome");
    $stmt->execute([$userId]);
    $temas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela não existir, continuar com array vazio
}

// Temas pré-definidos
$temas_predefinidos = [
    'padrao' => [
        'nome' => 'Padrão',
        'cores' => [
            'primary' => '#e50914',
            'secondary' => '#6c757d',
            'success' => '#00b894',
            'danger' => '#e50914',
            'warning' => '#f9a826',
            'info' => '#0984e3',
            'light' => '#f8f9fa',
            'dark' => '#212529',
            'background' => '#111111',
            'surface' => 'rgba(30, 30, 30, 0.5)',
            'text' => '#f5f5f1',
            'text_secondary' => '#adb5bd'
        ]
    ],
    'oceano' => [
        'nome' => 'Oceano',
        'cores' => [
            'primary' => '#007bff',
            'secondary' => '#6c757d',
            'success' => '#28a745',
            'danger' => '#dc3545',
            'warning' => '#ffc107',
            'info' => '#17a2b8',
            'light' => '#f8f9fa',
            'dark' => '#343a40',
            'background' => '#0a1929',
            'surface' => 'rgba(0, 123, 255, 0.1)',
            'text' => '#e3f2fd',
            'text_secondary' => '#90caf9'
        ]
    ],
    'floresta' => [
        'nome' => 'Floresta',
        'cores' => [
            'primary' => '#2e7d32',
            'secondary' => '#6c757d',
            'success' => '#4caf50',
            'danger' => '#f44336',
            'warning' => '#ff9800',
            'info' => '#2196f3',
            'light' => '#f8f9fa',
            'dark' => '#1b5e20',
            'background' => '#0d1b0d',
            'surface' => 'rgba(46, 125, 50, 0.1)',
            'text' => '#e8f5e8',
            'text_secondary' => '#a5d6a7'
        ]
    ],
    'por_do_sol' => [
        'nome' => 'Pôr do Sol',
        'cores' => [
            'primary' => '#ff6b35',
            'secondary' => '#6c757d',
            'success' => '#4caf50',
            'danger' => '#f44336',
            'warning' => '#ff9800',
            'info' => '#2196f3',
            'light' => '#f8f9fa',
            'dark' => '#bf360c',
            'background' => '#1a0f0a',
            'surface' => 'rgba(255, 107, 53, 0.1)',
            'text' => '#fff3e0',
            'text_secondary' => '#ffcc80'
        ]
    ],
    'roxo' => [
        'nome' => 'Roxo',
        'cores' => [
            'primary' => '#9c27b0',
            'secondary' => '#6c757d',
            'success' => '#4caf50',
            'danger' => '#f44336',
            'warning' => '#ff9800',
            'info' => '#2196f3',
            'light' => '#f8f9fa',
            'dark' => '#4a148c',
            'background' => '#1a0d1a',
            'surface' => 'rgba(156, 39, 176, 0.1)',
            'text' => '#f3e5f5',
            'text_secondary' => '#ce93d8'
        ]
    ],
    'minimalista' => [
        'nome' => 'Minimalista',
        'cores' => [
            'primary' => '#000000',
            'secondary' => '#6c757d',
            'success' => '#28a745',
            'danger' => '#dc3545',
            'warning' => '#ffc107',
            'info' => '#17a2b8',
            'light' => '#f8f9fa',
            'dark' => '#212529',
            'background' => '#ffffff',
            'surface' => 'rgba(0, 0, 0, 0.05)',
            'text' => '#212529',
            'text_secondary' => '#6c757d'
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
    .theme-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .theme-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .theme-card.active {
        border-color: var(--accent-red);
        box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.3);
    }
    .theme-preview {
        height: 120px;
        border-radius: 8px;
        margin-bottom: 1rem;
        position: relative;
        overflow: hidden;
    }
    .theme-preview::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--theme-bg);
    }
    .theme-preview::after {
        content: '';
        position: absolute;
        top: 20px;
        left: 20px;
        right: 20px;
        height: 20px;
        background: var(--theme-primary);
        border-radius: 4px;
    }
    .color-picker {
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    .color-picker:hover {
        transform: scale(1.1);
    }
    .customizer-panel {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 1.5rem;
    }
    .preview-container {
        background: var(--theme-bg);
        color: var(--theme-text);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-palette display-1 text-danger mb-4"></i>
        <h1 class="display-5">Temas Customizáveis</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Personalize completamente a aparência do seu painel. Escolha entre temas pré-definidos ou crie seu próprio tema personalizado com cores únicas.</p>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Temas Pré-definidos -->
    <div class="col-12">
        <div class="card theme-card">
            <div class="card-header">
                <h4 class="card-title mb-0">Temas Pré-definidos</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($temas_predefinidos as $key => $tema): ?>
                        <div class="col-md-4 col-lg-2">
                            <div class="theme-card text-center <?php echo $tema_ativo === $key ? 'active' : ''; ?>" 
                                 data-theme="<?php echo $key; ?>" 
                                 data-colors='<?php echo json_encode($tema['cores']); ?>'>
                                <div class="theme-preview" style="--theme-bg: <?php echo $tema['cores']['background']; ?>; --theme-primary: <?php echo $tema['cores']['primary']; ?>;"></div>
                                <h6 class="mb-0"><?php echo $tema['nome']; ?></h6>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Construtor de Temas -->
    <div class="col-lg-8">
        <div class="card theme-card">
            <div class="card-header">
                <h4 class="card-title mb-0">Construtor de Temas</h4>
            </div>
            <div class="card-body">
                <form id="formTemaCustomizado">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nome_tema" class="form-label">Nome do Tema</label>
                            <input type="text" class="form-control" id="nome_tema" name="nome_tema" placeholder="Ex: Meu Tema Personalizado">
                        </div>
                        <div class="col-md-6">
                            <label for="base_tema" class="form-label">Base do Tema</label>
                            <select class="form-select" id="base_tema" name="base_tema">
                                <option value="padrao">Padrão</option>
                                <option value="oceano">Oceano</option>
                                <option value="floresta">Floresta</option>
                                <option value="por_do_sol">Pôr do Sol</option>
                                <option value="roxo">Roxo</option>
                                <option value="minimalista">Minimalista</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-white-50 mb-3">Cores Personalizadas</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Cor Primária</label>
                                <input type="color" class="form-control color-picker" id="cor_primary" name="cor_primary" value="#e50914">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Fundo</label>
                                <input type="color" class="form-control color-picker" id="cor_background" name="cor_background" value="#111111">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Texto</label>
                                <input type="color" class="form-control color-picker" id="cor_text" name="cor_text" value="#f5f5f1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Sucesso</label>
                                <input type="color" class="form-control color-picker" id="cor_success" name="cor_success" value="#00b894">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Aviso</label>
                                <input type="color" class="form-control color-picker" id="cor_warning" name="cor_warning" value="#f9a826">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Informação</label>
                                <input type="color" class="form-control color-picker" id="cor_info" name="cor_info" value="#0984e3">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-save me-2"></i>Salvar Tema Personalizado
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="btnPreview">
                            <i class="bi bi-eye me-2"></i>Preview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview e Temas Salvos -->
    <div class="col-lg-4">
        <div class="card theme-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Preview</h5>
            </div>
            <div class="card-body">
                <div class="preview-container" id="previewContainer">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary rounded me-2" style="width: 20px; height: 20px;"></div>
                        <span>Card de Exemplo</span>
                    </div>
                    <p class="text-muted">Este é um exemplo de como seu tema ficará.</p>
                    <button class="btn btn-primary btn-sm">Botão Primário</button>
                    <button class="btn btn-success btn-sm ms-2">Sucesso</button>
                </div>
            </div>
        </div>

        <!-- Temas Salvos -->
        <?php if (!empty($temas)): ?>
            <div class="card theme-card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">Meus Temas</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($temas as $tema): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($tema['nome']); ?></span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-aplicar-tema" data-id="<?php echo $tema['id']; ?>">
                                    <i class="bi bi-check"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-excluir-tema" data-id="<?php echo $tema['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Aplicar tema pré-definido
    document.querySelectorAll('[data-theme]').forEach(card => {
        card.addEventListener('click', function() {
            const themeKey = this.dataset.theme;
            const colors = JSON.parse(this.dataset.colors);
            
            // Remover active de todos
            document.querySelectorAll('[data-theme]').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            
            // Aplicar tema
            aplicarTema(colors);
            
            // Salvar no localStorage
            localStorage.setItem('tema_ativo', themeKey);
            localStorage.setItem('cores_tema', JSON.stringify(colors));
        });
    });
    
    // Preview de tema customizado
    document.getElementById('btnPreview').addEventListener('click', function() {
        const cores = {
            primary: document.getElementById('cor_primary').value,
            background: document.getElementById('cor_background').value,
            text: document.getElementById('cor_text').value,
            success: document.getElementById('cor_success').value,
            warning: document.getElementById('cor_warning').value,
            info: document.getElementById('cor_info').value
        };
        
        aplicarTema(cores);
    });
    
    // Aplicar tema
    function aplicarTema(cores) {
        const root = document.documentElement;
        root.style.setProperty('--accent-red', cores.primary);
        root.style.setProperty('--dark-bg', cores.background);
        root.style.setProperty('--text-light', cores.text);
        root.style.setProperty('--success-color', cores.success);
        root.style.setProperty('--info-color', cores.info);
        root.style.setProperty('--warning-color', cores.warning);
        
        // Salvar na sessão via AJAX
        fetch('aplicar_tema_cores.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cores: cores })
        });
        
        // Atualizar preview
        const preview = document.getElementById('previewContainer');
        preview.style.setProperty('--theme-bg', cores.background);
        preview.style.setProperty('--theme-text', cores.text);
        preview.style.setProperty('--theme-primary', cores.primary);
    }
    
    // Carregar tema salvo
    const temaSalvo = localStorage.getItem('tema_ativo');
    const coresSalvas = localStorage.getItem('cores_tema');
    
    if (temaSalvo && coresSalvas) {
        const cores = JSON.parse(coresSalvas);
        aplicarTema(cores);
        
        // Marcar como ativo
        document.querySelectorAll('[data-theme]').forEach(card => {
            if (card.dataset.theme === temaSalvo) {
                card.classList.add('active');
            }
        });
    }
    
    // Formulário de tema customizado
    document.getElementById('formTemaCustomizado').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const cores = {
            primary: document.getElementById('cor_primary').value,
            background: document.getElementById('cor_background').value,
            text: document.getElementById('cor_text').value,
            success: document.getElementById('cor_success').value,
            warning: document.getElementById('cor_warning').value,
            info: document.getElementById('cor_info').value
        };
        
        formData.append('cores', JSON.stringify(cores));
        
        fetch('salvar_tema_personalizado.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Tema salvo com sucesso!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Erro!', data.message, true);
            }
        });
    });
    
    // Aplicar tema salvo
    document.querySelectorAll('.btn-aplicar-tema').forEach(btn => {
        btn.addEventListener('click', function() {
            const temaId = this.dataset.id;
            
            fetch('aplicar_tema_personalizado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tema_id: temaId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Tema aplicado!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            });
        });
    });
    
    // Excluir tema
    document.querySelectorAll('.btn-excluir-tema').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Tem certeza que deseja excluir este tema?')) {
                const temaId = this.dataset.id;
                
                fetch('excluir_tema_personalizado.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tema_id: temaId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', 'Tema excluído!');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, true);
                    }
                });
            }
        });
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
