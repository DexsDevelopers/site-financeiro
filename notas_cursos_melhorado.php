<?php
// notas_cursos_melhorado.php - Sistema Profissional de Notas e Anotações

require_once 'templates/header.php';

// Buscar cursos e notas
$cursos = [];
$notas = [];
$categoria_selecionada = $_GET['categoria'] ?? 'todas';
$curso_selecionado = $_GET['curso'] ?? 'todos';
$busca = $_GET['busca'] ?? '';

try {
    // Buscar cursos
    $stmt_cursos = $pdo->prepare("SELECT * FROM cursos WHERE id_usuario = ? ORDER BY nome_curso ASC");
    $stmt_cursos->execute([$userId]);
    $cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar notas com filtros
    $sql_notas = "SELECT n.*, c.nome_curso FROM notas_cursos n 
                  LEFT JOIN cursos c ON n.id_curso = c.id 
                  WHERE n.id_usuario = ?";
    $params = [$userId];
    
    if ($categoria_selecionada !== 'todas') {
        $sql_notas .= " AND n.categoria = ?";
        $params[] = $categoria_selecionada;
    }
    
    if ($curso_selecionado !== 'todos') {
        $sql_notas .= " AND n.id_curso = ?";
        $params[] = $curso_selecionado;
    }
    
    if (!empty($busca)) {
        $sql_notas .= " AND (n.titulo LIKE ? OR n.conteudo LIKE ?)";
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
    }
    
    $sql_notas .= " ORDER BY n.data_criacao DESC";
    
    $stmt_notas = $pdo->prepare($sql_notas);
    $stmt_notas->execute($params);
    $notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $cursos = [];
    $notas = [];
}

// Categorias disponíveis
$categorias = [
    'todas' => 'Todas as Categorias',
    'conceitos' => 'Conceitos',
    'exercicios' => 'Exercícios',
    'dicas' => 'Dicas',
    'resumos' => 'Resumos',
    'formulas' => 'Fórmulas',
    'definicoes' => 'Definições',
    'exemplos' => 'Exemplos',
    'outros' => 'Outros'
];

// Estatísticas
$stats = [
    'total_notas' => count($notas),
    'total_cursos' => count($cursos),
    'categorias_unicas' => count(array_unique(array_column($notas, 'categoria'))),
    'notas_hoje' => count(array_filter($notas, function($n) { 
        return date('Y-m-d', strtotime($n['data_criacao'])) === date('Y-m-d'); 
    }))
];
?>

<style>
    .intro-card {
        background: linear-gradient(135deg, rgba(30, 30, 30, 0.5) 0%, rgba(50, 30, 30, 0.5) 100%);
    }
    
    .stats-card {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(229, 9, 20, 0.05) 100%);
        border: 1px solid rgba(229, 9, 20, 0.3);
        transition: all 0.3s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(229, 9, 20, 0.2);
    }
    
    .note-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .note-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border-color: var(--accent-red);
    }
    
    .note-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-red), #ff6b6b);
    }
    
    .note-content {
        max-height: 150px;
        overflow-y: auto;
        line-height: 1.6;
    }
    
    .note-content::-webkit-scrollbar {
        width: 4px;
    }
    
    .note-content::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
    }
    
    .note-content::-webkit-scrollbar-thumb {
        background: var(--accent-red);
        border-radius: 2px;
    }
    
    .category-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: 600;
    }
    
    .search-box {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }
    
    .search-box:focus {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent-red);
        box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
    }
    
    .filter-card {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1.5rem;
    }
    
    .course-selector {
        background: var(--card-background);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 1rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .note-actions {
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .note-card:hover .note-actions {
        opacity: 1;
    }
    
    .note-priority {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .priority-alta { background: #dc3545; }
    .priority-media { background: #ffc107; }
    .priority-baixa { background: #28a745; }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-journal-text display-1 text-danger mb-4"></i>
        <h1 class="display-5">📚 Notas e Anotações</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Sistema profissional de organização de anotações com categorização inteligente e busca avançada.</p>
    </div>
</div>

<!-- Estatísticas -->
<div class="row g-4 mt-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-journal-text feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['total_notas']; ?></h5>
                <p class="text-white-50 mb-0">Total de Notas</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-book feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['total_cursos']; ?></h5>
                <p class="text-white-50 mb-0">Cursos</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-tags feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['categorias_unicas']; ?></h5>
                <p class="text-white-50 mb-0">Categorias</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="bi bi-calendar feature-icon mb-3"></i>
                <h5 class="card-title"><?php echo $stats['notas_hoje']; ?></h5>
                <p class="text-white-50 mb-0">Criadas Hoje</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtros e Busca -->
<div class="row g-4 mt-4">
    <div class="col-12">
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-search me-2"></i>Buscar
                        </label>
                        <input type="text" name="busca" class="form-control search-box" 
                               placeholder="Digite para buscar..." value="<?php echo htmlspecialchars($busca); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-tags me-2"></i>Categoria
                        </label>
                        <select name="categoria" class="form-select">
                            <?php foreach ($categorias as $key => $nome): ?>
                                <option value="<?php echo $key; ?>" <?php echo $categoria_selecionada === $key ? 'selected' : ''; ?>>
                                    <?php echo $nome; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-book me-2"></i>Curso
                        </label>
                        <select name="curso" class="form-select">
                            <option value="todos" <?php echo $curso_selecionado === 'todos' ? 'selected' : ''; ?>>Todos os Cursos</option>
                            <?php foreach ($cursos as $curso): ?>
                                <option value="<?php echo $curso['id']; ?>" <?php echo $curso_selecionado == $curso['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($curso['nome_curso']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-funnel me-2"></i>Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Botão Nova Nota -->
<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-journal-text me-2"></i>Suas Anotações
                <span class="badge bg-secondary ms-2"><?php echo count($notas); ?></span>
            </h4>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
                <i class="bi bi-plus-circle me-2"></i>Nova Anotação
            </button>
        </div>
    </div>
</div>

<!-- Lista de Notas -->
<div class="row g-4 mt-3">
    <?php if (empty($notas)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body empty-state">
                    <i class="bi bi-journal-x"></i>
                    <h5>Nenhuma anotação encontrada</h5>
                    <p class="text-muted">Comece criando sua primeira anotação ou ajuste os filtros de busca.</p>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
                        <i class="bi bi-plus-circle me-2"></i>Criar Primeira Anotação
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($notas as $nota): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card note-card h-100">
                    <div class="note-priority priority-<?php echo $nota['prioridade'] ?? 'baixa'; ?>"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="card-title mb-0 fw-bold">
                                <?php echo htmlspecialchars($nota['titulo']); ?>
                            </h6>
                            <div class="note-actions">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="editarNota(<?php echo $nota['id']; ?>)">
                                            <i class="bi bi-pencil me-2"></i>Editar
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" onclick="duplicarNota(<?php echo $nota['id']; ?>)">
                                            <i class="bi bi-files me-2"></i>Duplicar
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="excluirNota(<?php echo $nota['id']; ?>)">
                                            <i class="bi bi-trash me-2"></i>Excluir
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="note-content mb-3">
                            <?php echo nl2br(htmlspecialchars(substr($nota['conteudo'], 0, 200))); ?>
                            <?php if (strlen($nota['conteudo']) > 200): ?>
                                <span class="text-muted">...</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="category-badge bg-<?php echo $nota['categoria'] === 'conceitos' ? 'primary' : ($nota['categoria'] === 'exercicios' ? 'success' : ($nota['categoria'] === 'dicas' ? 'warning' : 'secondary')); ?>">
                                    <?php echo ucfirst($nota['categoria']); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php echo date('d/m/Y', strtotime($nota['data_criacao'])); ?>
                            </small>
                        </div>
                        
                        <?php if ($nota['nome_curso']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-book me-1"></i>
                                    <?php echo htmlspecialchars($nota['nome_curso']); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Nova Nota -->
<div class="modal fade" id="modalNovaNota" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Nova Anotação
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovaNota">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Título da Anotação</label>
                            <input type="text" name="titulo" class="form-control" placeholder="Ex: Conceitos de JavaScript" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Categoria</label>
                            <select name="categoria" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($categorias as $key => $nome): ?>
                                    <?php if ($key !== 'todas'): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $nome; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Curso Relacionado</label>
                            <select name="id_curso" class="form-select">
                                <option value="">Selecione um curso (opcional)</option>
                                <?php foreach ($cursos as $curso): ?>
                                    <option value="<?php echo $curso['id']; ?>">
                                        <?php echo htmlspecialchars($curso['nome_curso']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="baixa">Baixa</option>
                                <option value="media">Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Conteúdo</label>
                            <textarea name="conteudo" class="form-control" rows="8" 
                                      placeholder="Digite sua anotação aqui..." required></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnSalvarNota">
                    <i class="bi bi-check-lg me-2"></i>Salvar Anotação
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Salvar nova nota
    document.getElementById('btnSalvarNota').addEventListener('click', function() {
        const form = document.getElementById('formNovaNota');
        const formData = new FormData(form);
        const button = this;
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
        
        fetch('salvar_nota_curso.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Anotação salva com sucesso!');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Erro!', data.message, true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Anotação';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro de conexão', true);
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Anotação';
        });
    });
});

// Funções para ações das notas
function editarNota(id) {
    // Implementar edição
    showToast('Info', 'Funcionalidade de edição em desenvolvimento');
}

function duplicarNota(id) {
    // Implementar duplicação
    showToast('Info', 'Funcionalidade de duplicação em desenvolvimento');
}

function excluirNota(id) {
    if (confirm('Tem certeza que deseja excluir esta anotação?')) {
        fetch('excluir_nota_curso.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sucesso!', 'Anotação excluída com sucesso!');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('Erro!', data.message, true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro de conexão', true);
        });
    }
}
</script>

<?php
require_once 'templates/footer.php';
?>
