<?php
// notas_cursos.php - Sistema Profissional de Notas e Anotações com Mapa Mental
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
    error_log("Erro ao buscar notas: " . $e->getMessage());
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
        background: var(--card-background, #1a1a1a);
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        color: var(--bs-body-color, #fff);
    }
    
    .note-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border-color: var(--accent-red, #dc3545);
    }
    
    .note-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-red, #dc3545), #ff6b6b);
    }
    
    .note-content {
        max-height: 150px;
        overflow-y: auto;
        line-height: 1.6;
        color: var(--bs-body-color, #fff);
    }
    
    .note-content::-webkit-scrollbar {
        width: 4px;
    }
    
    .note-content::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 2px;
    }
    
    .note-content::-webkit-scrollbar-thumb {
        background: var(--accent-red, #dc3545);
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
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        color: var(--bs-body-color, #fff);
    }
    
    .search-box:focus {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent-red, #dc3545);
        box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
        color: var(--bs-body-color, #fff);
    }
    
    .filter-card {
        background: var(--card-background, #1a1a1a);
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        padding: 1.5rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary, #999);
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
    
    /* Mapa Mental Styles */
    #mindmap-container {
        width: 100%;
        height: 600px;
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        background: var(--card-background, #1a1a1a);
        position: relative;
        overflow: hidden;
    }
    
    .mindmap-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1000;
        display: flex;
        gap: 5px;
    }
    
    .mindmap-node {
        cursor: pointer;
        user-select: none;
    }
    
    .mindmap-toolbar {
        background: var(--card-background, #1a1a1a);
        border: 1px solid var(--border-color, #333);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-journal-text display-1 text-danger mb-4"></i>
        <h1 class="display-5">📚 Notas e Anotações</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Sistema profissional de organização de anotações com categorização inteligente, busca avançada e mapas mentais.</p>
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

<!-- Abas: Notas e Mapa Mental -->
<div class="row mt-4">
    <div class="col-12">
        <ul class="nav nav-tabs" id="notasTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="notas-tab" data-bs-toggle="tab" data-bs-target="#notas" type="button" role="tab">
                    <i class="bi bi-journal-text me-2"></i>Notas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mapa-tab" data-bs-toggle="tab" data-bs-target="#mapa" type="button" role="tab">
                    <i class="bi bi-diagram-3 me-2"></i>Mapa Mental
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content mt-4" id="notasTabsContent">
    <!-- Aba Notas -->
    <div class="tab-pane fade show active" id="notas" role="tabpanel">
        <!-- Filtros e Busca -->
        <div class="row g-4">
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
                                    <h6 class="card-title mb-0 fw-bold" style="color: var(--bs-body-color, #fff);">
                                        <?php echo htmlspecialchars($nota['titulo']); ?>
                                    </h6>
                                    <div class="note-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="editarNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-pencil me-2"></i>Editar
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="duplicarNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-files me-2"></i>Duplicar
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="criarMapaMental(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-diagram-3 me-2"></i>Criar Mapa Mental
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="excluirNota(<?php echo $nota['id']; ?>); return false;">
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
    </div>

    <!-- Aba Mapa Mental -->
    <div class="tab-pane fade" id="mapa" role="tabpanel">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">
                        <i class="bi bi-diagram-3 me-2"></i>Mapas Mentais
                    </h4>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovoMapa">
                        <i class="bi bi-plus-circle me-2"></i>Novo Mapa Mental
                    </button>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div id="mapas-mentais-lista" class="row g-3">
                    <!-- Mapas mentais serão carregados aqui via JavaScript -->
                </div>
            </div>
        </div>
    </div>
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

<!-- Modal Editar Nota -->
<div class="modal fade" id="modalEditarNota" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil me-2"></i>Editar Anotação
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarNota">
                    <input type="hidden" name="id" id="edit-nota-id">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Título da Anotação</label>
                            <input type="text" name="titulo" id="edit-nota-titulo" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Categoria</label>
                            <select name="categoria" id="edit-nota-categoria" class="form-select" required>
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
                            <select name="id_curso" id="edit-nota-curso" class="form-select">
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
                            <select name="prioridade" id="edit-nota-prioridade" class="form-select">
                                <option value="baixa">Baixa</option>
                                <option value="media">Média</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Conteúdo</label>
                            <textarea name="conteudo" id="edit-nota-conteudo" class="form-control" rows="8" required></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnAtualizarNota">
                    <i class="bi bi-check-lg me-2"></i>Atualizar Anotação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Mapa Mental -->
<div class="modal fade" id="modalNovoMapa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-diagram-3 me-2"></i>Novo Mapa Mental
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Título do Mapa Mental</label>
                    <input type="text" id="mapa-titulo" class="form-control" placeholder="Ex: Estrutura de Dados">
                </div>
                <div id="mindmap-container"></div>
                <div class="mindmap-controls">
                    <button class="btn btn-sm btn-primary" onclick="adicionarNoMapa()" title="Adicionar Nó">
                        <i class="bi bi-plus-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-success" onclick="salvarMapaMental()" title="Salvar">
                        <i class="bi bi-save"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="limparMapa()" title="Limpar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="salvarMapaMental()">
                    <i class="bi bi-check-lg me-2"></i>Salvar Mapa Mental
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizar Mapa Mental -->
<div class="modal fade" id="modalVisualizarMapa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="mapa-visualizar-titulo">
                    <i class="bi bi-diagram-3 me-2"></i>Mapa Mental
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="mindmap-visualizar-container" style="width: 100%; height: 600px; border: 1px solid var(--border-color, #333); border-radius: 12px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Biblioteca vis-network para mapas mentais -->
<script src="https://unpkg.com/vis-network@latest/standalone/umd/vis-network.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
    let network = null;
    let nodes = new vis.DataSet([]);
    let edges = new vis.DataSet([]);
    let nodeIdCounter = 1;
    
    // Inicializar mapa mental
    function inicializarMapaMental(containerId, dadosIniciais = null) {
        const container = document.getElementById(containerId);
        if (!container) return null;
        
        if (dadosIniciais) {
            nodes = new vis.DataSet(dadosIniciais.nodes || []);
            edges = new vis.DataSet(dadosIniciais.edges || []);
            if (dadosIniciais.nodes && dadosIniciais.nodes.length > 0) {
                nodeIdCounter = Math.max(...dadosIniciais.nodes.map(n => n.id)) + 1;
            }
        } else {
            // Nó central padrão
            nodes = new vis.DataSet([{ id: 1, label: 'Tema Central', shape: 'box', color: { background: '#dc3545', border: '#c82333' } }]);
            edges = new vis.DataSet([]);
            nodeIdCounter = 2;
        }
        
        const data = { nodes: nodes, edges: edges };
        const options = {
            nodes: {
                shape: 'box',
                font: { color: '#fff', size: 14 },
                borderWidth: 2,
                shadow: true
            },
            edges: {
                arrows: { to: { enabled: true } },
                color: { color: '#dc3545' },
                width: 2
            },
            physics: {
                enabled: true,
                stabilization: { iterations: 200 }
            },
            interaction: {
                dragNodes: true,
                dragView: true,
                zoomView: true
            }
        };
        
        const networkInstance = new vis.Network(container, data, options);
        
        // Adicionar nó ao clicar duas vezes
        networkInstance.on('doubleClick', function(params) {
            if (params.nodes.length === 0) {
                // Criar novo nó na posição do clique
                const pos = networkInstance.getPositionOnCanvas(params.pointer.canvas);
                const novoNo = {
                    id: nodeIdCounter++,
                    label: 'Novo Nó',
                    x: pos.x,
                    y: pos.y,
                    shape: 'box',
                    color: { background: '#6c757d', border: '#5a6268' }
                };
                nodes.add(novoNo);
            }
        });
        
        return networkInstance;
    }
    
    // Salvar nova nota
    const btnSalvarNota = document.getElementById('btnSalvarNota');
    if (btnSalvarNota) {
        btnSalvarNota.addEventListener('click', function() {
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
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovaNota'));
                    if (modal) modal.hide();
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
    }
    
    // Atualizar nota
    const btnAtualizarNota = document.getElementById('btnAtualizarNota');
    if (btnAtualizarNota) {
        btnAtualizarNota.addEventListener('click', function() {
            const form = document.getElementById('formEditarNota');
            const formData = new FormData(form);
            const button = this;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Atualizando...';
            
            fetch('atualizar_nota_curso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', 'Anotação atualizada com sucesso!');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarNota'));
                    if (modal) modal.hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Atualizar Anotação';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                showToast('Erro!', 'Erro de conexão', true);
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-check-lg me-2"></i>Atualizar Anotação';
            });
        });
    }
    
    // Inicializar mapa mental quando modal abrir
    const modalNovoMapa = document.getElementById('modalNovoMapa');
    if (modalNovoMapa) {
        modalNovoMapa.addEventListener('shown.bs.modal', function() {
            if (!network) {
                network = inicializarMapaMental('mindmap-container');
            }
        });
    }
    
    // Carregar mapas mentais salvos
    function carregarMapasMentais() {
        fetch('buscar_mapas_mentais.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.mapas) {
                    const container = document.getElementById('mapas-mentais-lista');
                    if (container) {
                        if (data.mapas.length === 0) {
                            container.innerHTML = `
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body text-center p-5">
                                            <i class="bi bi-diagram-3 fs-1 text-muted"></i>
                                            <h5 class="mt-3 text-muted">Nenhum mapa mental criado</h5>
                                            <p class="text-muted mb-0">Crie seu primeiro mapa mental para visualizar conceitos de forma interativa.</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            container.innerHTML = data.mapas.map(mapa => `
                                <div class="col-md-6 col-lg-4">
                                    <div class="card note-card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title fw-bold" style="color: var(--bs-body-color, #fff);">${escapeHTML(mapa.titulo)}</h6>
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-calendar me-1"></i>
                                                ${new Date(mapa.data_criacao).toLocaleDateString('pt-BR')}
                                            </p>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-primary" onclick="visualizarMapa(${mapa.id}, '${escapeHTML(mapa.titulo)}', ${escapeHTML(JSON.stringify(mapa.dados))})">
                                                    <i class="bi bi-eye me-1"></i>Visualizar
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="excluirMapa(${mapa.id})">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `).join('');
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar mapas:', error);
            });
    }
    
    // Carregar mapas quando a aba for ativada
    const mapaTab = document.getElementById('mapa-tab');
    if (mapaTab) {
        mapaTab.addEventListener('shown.bs.tab', function() {
            carregarMapasMentais();
        });
    }
});

// Função para escapar HTML
function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Funções para ações das notas
function editarNota(id) {
    fetch(`buscar_nota.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.nota) {
                const nota = data.nota;
                document.getElementById('edit-nota-id').value = nota.id;
                document.getElementById('edit-nota-titulo').value = nota.titulo || '';
                document.getElementById('edit-nota-categoria').value = nota.categoria || '';
                document.getElementById('edit-nota-curso').value = nota.id_curso || '';
                document.getElementById('edit-nota-prioridade').value = nota.prioridade || 'baixa';
                document.getElementById('edit-nota-conteudo').value = nota.conteudo || '';
                
                const modal = new bootstrap.Modal(document.getElementById('modalEditarNota'));
                modal.show();
            } else {
                showToast('Erro!', data.message || 'Nota não encontrada', true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro de conexão', true);
        });
}

function duplicarNota(id) {
    if (!confirm('Deseja duplicar esta anotação?')) return;
    
    fetch('duplicar_nota_curso.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Anotação duplicada com sucesso!');
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

function excluirNota(id) {
    if (!confirm('Tem certeza que deseja excluir esta anotação?')) return;
    
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

// Variáveis globais para mapa mental
let networkMapa = null;
let nodesMapa = null;
let edgesMapa = null;
let nodeIdCounterMapa = 1;

// Funções para mapa mental (escopo global)
function adicionarNoMapa() {
    if (!nodesMapa) {
        nodesMapa = new vis.DataSet([{ id: 1, label: 'Tema Central', shape: 'box', color: { background: '#dc3545', border: '#c82333' } }]);
        edgesMapa = new vis.DataSet([]);
        nodeIdCounterMapa = 2;
    }
    
    const novoNo = {
        id: nodeIdCounterMapa++,
        label: 'Novo Nó',
        shape: 'box',
        color: { background: '#6c757d', border: '#5a6268' }
    };
    nodesMapa.add(novoNo);
    
    // Conectar ao nó central se existir
    if (nodesMapa.length > 1) {
        edgesMapa.add({ from: 1, to: novoNo.id });
    }
    
    atualizarRedeMapa();
}

function atualizarRedeMapa() {
    if (!networkMapa || !nodesMapa || !edgesMapa) return;
    
    const data = { nodes: nodesMapa, edges: edgesMapa };
    networkMapa.setData(data);
}

function salvarMapaMental() {
    const titulo = document.getElementById('mapa-titulo')?.value?.trim();
    if (!titulo) {
        showToast('Erro!', 'Título é obrigatório', true);
        return;
    }
    
    if (!nodesMapa || nodesMapa.length === 0) {
        showToast('Erro!', 'Adicione pelo menos um nó ao mapa', true);
        return;
    }
    
    const dados = {
        nodes: nodesMapa.get(),
        edges: edgesMapa ? edgesMapa.get() : []
    };
    
    fetch('salvar_mapa_mental.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ titulo: titulo, dados: JSON.stringify(dados) })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Mapa mental salvo com sucesso!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovoMapa'));
            if (modal) modal.hide();
            
            // Limpar e resetar
            document.getElementById('mapa-titulo').value = '';
            nodesMapa = null;
            edgesMapa = null;
            nodeIdCounterMapa = 1;
            
            // Recarregar lista se estiver na aba de mapas
            const mapaTab = document.getElementById('mapa-tab');
            if (mapaTab && mapaTab.classList.contains('active')) {
                setTimeout(() => window.location.reload(), 1000);
            }
        } else {
            showToast('Erro!', data.message, true);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showToast('Erro!', 'Erro de conexão', true);
    });
}

function limparMapa() {
    if (!confirm('Deseja limpar o mapa mental? Esta ação não pode ser desfeita.')) return;
    
    nodesMapa = new vis.DataSet([{ id: 1, label: 'Tema Central', shape: 'box', color: { background: '#dc3545', border: '#c82333' } }]);
    edgesMapa = new vis.DataSet([]);
    nodeIdCounterMapa = 2;
    atualizarRedeMapa();
}

function visualizarMapa(id, titulo, dadosJson) {
    const dados = typeof dadosJson === 'string' ? JSON.parse(dadosJson) : dadosJson;
    
    document.getElementById('mapa-visualizar-titulo').innerHTML = `<i class="bi bi-diagram-3 me-2"></i>${escapeHTML(titulo)}`;
    
    const container = document.getElementById('mindmap-visualizar-container');
    if (container) {
        const nodes = new vis.DataSet(dados.nodes || []);
        const edges = new vis.DataSet(dados.edges || []);
        const data = { nodes: nodes, edges: edges };
        
        const options = {
            nodes: {
                shape: 'box',
                font: { color: '#fff', size: 14 },
                borderWidth: 2,
                shadow: true
            },
            edges: {
                arrows: { to: { enabled: true } },
                color: { color: '#dc3545' },
                width: 2
            },
            physics: {
                enabled: true,
                stabilization: { iterations: 200 }
            },
            interaction: {
                dragNodes: true,
                dragView: true,
                zoomView: true
            }
        };
        
        const network = new vis.Network(container, data, options);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modalVisualizarMapa'));
    modal.show();
}

function excluirMapa(id) {
    if (!confirm('Tem certeza que deseja excluir este mapa mental?')) return;
    
    fetch('excluir_mapa_mental.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Sucesso!', 'Mapa mental excluído com sucesso!');
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

function criarMapaMental(notaId) {
    // Buscar nota e criar mapa mental baseado nela
    fetch(`buscar_nota.php?id=${notaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.nota) {
                const nota = data.nota;
                document.getElementById('mapa-titulo').value = nota.titulo + ' - Mapa Mental';
                
                // Criar nós baseados no conteúdo da nota
                const palavras = nota.conteudo.split(/\s+/).filter(p => p.length > 3).slice(0, 10);
                nodesMapa = new vis.DataSet([
                    { id: 1, label: nota.titulo, shape: 'box', color: { background: '#dc3545', border: '#c82333' } }
                ]);
                edgesMapa = new vis.DataSet([]);
                nodeIdCounterMapa = 2;
                
                palavras.forEach((palavra, index) => {
                    nodesMapa.add({
                        id: nodeIdCounterMapa++,
                        label: palavra.substring(0, 20),
                        shape: 'box',
                        color: { background: '#6c757d', border: '#5a6268' }
                    });
                    edgesMapa.add({ from: 1, to: nodeIdCounterMapa - 1 });
                });
                
                const modal = new bootstrap.Modal(document.getElementById('modalNovoMapa'));
                modal.show();
                
                setTimeout(() => {
                    const container = document.getElementById('mindmap-container');
                    if (container && !networkMapa) {
                        const data = { nodes: nodesMapa, edges: edgesMapa };
                        const options = {
                            nodes: {
                                shape: 'box',
                                font: { color: '#fff', size: 14 },
                                borderWidth: 2,
                                shadow: true
                            },
                            edges: {
                                arrows: { to: { enabled: true } },
                                color: { color: '#dc3545' },
                                width: 2
                            },
                            physics: {
                                enabled: true,
                                stabilization: { iterations: 200 }
                            },
                            interaction: {
                                dragNodes: true,
                                dragView: true,
                                zoomView: true
                            }
                        };
                        networkMapa = new vis.Network(container, data, options);
                    } else if (networkMapa) {
                        atualizarRedeMapa();
                    }
                }, 300);
            } else {
                showToast('Erro!', 'Nota não encontrada', true);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showToast('Erro!', 'Erro ao buscar nota', true);
        });
}

// Inicializar mapa mental quando modal abrir (dentro do DOMContentLoaded)
const modalNovoMapa = document.getElementById('modalNovoMapa');
if (modalNovoMapa) {
    modalNovoMapa.addEventListener('shown.bs.modal', function() {
        if (!networkMapa) {
            const container = document.getElementById('mindmap-container');
            if (container) {
                if (!nodesMapa) {
                    nodesMapa = new vis.DataSet([{ id: 1, label: 'Tema Central', shape: 'box', color: { background: '#dc3545', border: '#c82333' } }]);
                    edgesMapa = new vis.DataSet([]);
                    nodeIdCounterMapa = 2;
                }
                
                const data = { nodes: nodesMapa, edges: edgesMapa };
                const options = {
                    nodes: {
                        shape: 'box',
                        font: { color: '#fff', size: 14 },
                        borderWidth: 2,
                        shadow: true
                    },
                    edges: {
                        arrows: { to: { enabled: true } },
                        color: { color: '#dc3545' },
                        width: 2
                    },
                    physics: {
                        enabled: true,
                        stabilization: { iterations: 200 }
                    },
                    interaction: {
                        dragNodes: true,
                        dragView: true,
                        zoomView: true
                    }
                };
                
                networkMapa = new vis.Network(container, data, options);
                
                // Adicionar nó ao clicar duas vezes
                networkMapa.on('doubleClick', function(params) {
                    if (params.nodes.length === 0) {
                        const pos = networkMapa.getPositionOnCanvas(params.pointer.canvas);
                        const novoNo = {
                            id: nodeIdCounterMapa++,
                            label: 'Novo Nó',
                            x: pos.x,
                            y: pos.y,
                            shape: 'box',
                            color: { background: '#6c757d', border: '#5a6268' }
                        };
                        nodesMapa.add(novoNo);
                    } else {
                        // Editar nó existente
                        const nodeId = params.nodes[0];
                        const node = nodesMapa.get(nodeId);
                        const novoLabel = prompt('Digite o novo texto do nó:', node.label);
                        if (novoLabel !== null && novoLabel.trim() !== '') {
                            nodesMapa.update({ id: nodeId, label: novoLabel.trim() });
                        }
                    }
                });
            }
        }
    });
    
    modalNovoMapa.addEventListener('hidden.bs.modal', function() {
        // Resetar ao fechar
        const tituloInput = document.getElementById('mapa-titulo');
        if (tituloInput) tituloInput.value = '';
        nodesMapa = null;
        edgesMapa = null;
        nodeIdCounterMapa = 1;
        networkMapa = null;
    });
}
</script>

<?php
require_once 'templates/footer.php';
?>
