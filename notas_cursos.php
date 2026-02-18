<?php
// notas_cursos.php - Sistema Profissional de Notas e Anotações com Mapa Mental (Canvas Customizado)
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
        opacity: 0.6;
        transition: all 0.3s ease;
    }
    
    .note-card:hover .note-actions {
        opacity: 1;
        transform: scale(1.1);
    }

    
    .note-card {
        background: rgba(26, 26, 30, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    
    .note-card:hover {
        transform: translateY(-8px) scale(1.01);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border-color: rgba(229, 9, 20, 0.3);
    }
    
    .note-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #e50914, #ff4d4d);
        opacity: 0.8;
    }
    
    .note-priority {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        box-shadow: 0 0 10px currentColor;
    }
    
    .priority-alta { color: #dc3545; background: #dc3545; }
    .priority-media { color: #ffc107; background: #ffc107; }
    .priority-baixa { color: #28a745; background: #28a745; }

    .category-badge {
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 50px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    
    /* Mapa Mental Styles - Canvas Customizado Moderno */
    #mindmap-container {
        width: 100%;
        height: 600px;
        min-height: 400px;
        border: 2px solid var(--border-color, #333);
        border-radius: 16px;
        background: linear-gradient(135deg, #1a1a1a 0%, #2d1b1b 100%);
        position: relative;
        overflow: hidden;
        cursor: grab;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        transition: all 0.3s ease;
    }
    
    #mindmap-container:active {
        cursor: grabbing;
    }
    
    #mindmap-container:hover {
        border-color: var(--accent-red, #dc3545);
        box-shadow: 0 12px 40px rgba(229, 9, 20, 0.3);
    }
    
    #mindmap-canvas {
        width: 100%;
        height: 100%;
        display: block;
        touch-action: none;
    }
    
    .mindmap-controls {
        position: absolute;
        top: 15px;
        right: 15px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: rgba(26, 26, 26, 0.95);
        backdrop-filter: blur(10px);
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(229, 9, 20, 0.3);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }
    
    .mindmap-controls button {
        min-width: 120px;
        transition: all 0.3s ease;
        border-radius: 8px;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
    
    .mindmap-controls button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }
    
    .mindmap-controls button:active {
        transform: translateY(0);
    }
    
    .mindmap-node {
        cursor: pointer;
        user-select: none;
        transition: all 0.2s ease;
    }
    
    .mindmap-node:hover {
        transform: scale(1.05);
    }
    
    .mindmap-toolbar {
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(45, 27, 27, 0.95) 100%);
        border: 1px solid var(--border-color, #333);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        backdrop-filter: blur(10px);
    }
    
    .node-edit-input {
        position: absolute;
        background: rgba(26, 26, 26, 0.98);
        border: 2px solid #dc3545;
        border-radius: 8px;
        padding: 8px 12px;
        color: #fff;
        font-size: 14px;
        z-index: 10000;
        min-width: 200px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }
    
    /* Responsividade Mobile */
    @media (max-width: 768px) {
        #mindmap-container {
            height: 500px;
            min-height: 350px;
        }
        
        .mindmap-controls {
            top: 10px;
            right: 10px;
            padding: 8px;
            flex-direction: row;
            flex-wrap: wrap;
            max-width: calc(100% - 20px);
        }
        
        .mindmap-controls button {
            min-width: auto;
            flex: 1;
            font-size: 0.875rem;
            padding: 6px 10px;
        }
        
        .mindmap-controls button i {
            margin-right: 0 !important;
        }
        
        .mindmap-controls button span {
            display: none;
        }
    }
    
    @media (max-width: 576px) {
        #mindmap-container {
            height: 400px;
            min-height: 300px;
        }
        
        .mindmap-controls {
            position: relative;
            top: auto;
            right: auto;
            margin-top: 10px;
            width: 100%;
            justify-content: center;
        }
    }
    
    /* Indicador de zoom */
    .zoom-indicator {
        position: absolute;
        bottom: 15px;
        left: 15px;
        background: rgba(26, 26, 26, 0.9);
        backdrop-filter: blur(10px);
        padding: 8px 12px;
        border-radius: 8px;
        color: #fff;
        font-size: 0.875rem;
        border: 1px solid rgba(229, 9, 20, 0.3);
        z-index: 1000;
    }
    
    /* Loading overlay */
    .mindmap-loading {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(26, 26, 26, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        border-radius: 16px;
    }
    
    .mindmap-loading .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.3em;
    }
</style>

<div class="card card-custom intro-card border-0" data-aos="fade-up">
    <div class="card-body p-4 p-md-5 text-center">
        <i class="bi bi-journal-text display-1 text-danger mb-4"></i>
        <h1 class="display-5">📚 Notas e Anotações</h1>
        <p class="lead text-white-50 col-md-8 mx-auto">Sistema profissional de organização de anotações com categorização inteligente, busca avançada e mapas mentais interativos.</p>
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
                    <div class="card" style="background: rgba(30, 30, 30, 0.4); border: 2px dashed rgba(255, 255, 255, 0.1);">
                        <div class="card-body empty-state p-5">
                            <div class="mb-4">
                                <i class="bi bi-journal-x display-1 text-muted opacity-25"></i>
                            </div>
                            <h4 class="fw-bold">Nenhuma anotação encontrada</h4>
                            <p class="text-muted mb-4 mx-auto" style="max-width: 400px;">Capture seus aprendizados hoje mesmo! Suas notas ficarão organizadas por curso e categoria.</p>
                            <button class="btn btn-danger px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalNovaNota">
                                <i class="bi bi-plus-circle me-2"></i>Começar Minha Primeira Nota
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notas as $nota): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card note-card h-100">
                            <div class="note-priority priority-<?php echo $nota['prioridade'] ?? 'baixa'; ?>"></div>
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0 fw-bold text-white text-truncate pe-3" title="<?php echo htmlspecialchars($nota['titulo']); ?>">
                                        <?php echo htmlspecialchars($nota['titulo']); ?>
                                    </h6>
                                    <div class="note-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-white-50 p-0" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical fs-5"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg border-secondary">
                                                <li><a class="dropdown-item" href="#" onclick="visualizarNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-eye me-2 text-primary"></i>Visualizar
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="resumirComIA(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-stars me-2 text-warning"></i>Resumir com IA
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="editarNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-pencil me-2 text-info"></i>Editar
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="duplicarNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-files me-2 text-white-50"></i>Duplicar
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="criarMapaMental(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-diagram-3 me-2 text-success"></i>Transformar em Mapa
                                                </a></li>
                                                <li><hr class="dropdown-divider border-secondary"></li>
                                                <li><a class="dropdown-item text-danger" href="#" onclick="excluirNota(<?php echo $nota['id']; ?>); return false;">
                                                    <i class="bi bi-trash me-2"></i>Excluir
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="note-content mb-3" style="font-size: 0.9rem; opacity: 0.85;">
                                    <?php 
                                        $conteudo_curto = htmlspecialchars(substr($nota['conteudo'], 0, 180));
                                        echo nl2br($conteudo_curto); 
                                        if (strlen($nota['conteudo']) > 180) echo '<span class="text-danger fw-bold">... ler mais</span>';
                                    ?>
                                </div>
                                
                                <div class="mt-auto pt-3 border-top border-secondary border-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="category-badge" style="background: rgba(<?php 
                                            echo ($nota['categoria'] === 'conceitos' ? '13, 110, 253' : 
                                                 ($nota['categoria'] === 'exercicios' ? '25, 135, 84' : 
                                                 ($nota['categoria'] === 'dicas' ? '255, 193, 7' : '108, 117, 125'))); 
                                            ?>, 0.15); color: <?php 
                                            echo ($nota['categoria'] === 'conceitos' ? '#0d6efd' : 
                                                 ($nota['categoria'] === 'exercicios' ? '#198754' : 
                                                 ($nota['categoria'] === 'dicas' ? '#ffc107' : '#adb5bd'))); 
                                            ?>; border: 1px solid rgba(255,255,255,0.05);">
                                            <?php echo htmlspecialchars($categorias[$nota['categoria']] ?? 'Outros'); ?>
                                        </span>
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <?php echo date('d M, Y', strtotime($nota['data_criacao'])); ?>
                                        </small>
                                    </div>
                                    
                                    <?php if ($nota['nome_curso']): ?>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 bg-danger bg-opacity-10 rounded-circle p-1 me-2" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-book text-danger" style="font-size: 0.75rem;"></i>
                                            </div>
                                            <small class="text-white-50 text-truncate" style="font-size: 0.8rem;">
                                                <?php echo htmlspecialchars($nota['nome_curso']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer p-0 border-0 bg-transparent">
                                <button class="btn btn-link w-100 text-decoration-none text-white-50 py-2 border-top border-secondary border-opacity-10" 
                                        onclick="visualizarNota(<?php echo $nota['id']; ?>)" style="font-size: 0.8rem;">
                                    Ver Detalhes <i class="bi bi-arrow-right ms-1"></i>
                                </button>
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
                <div class="alert alert-info mb-3 border-0" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%); border-left: 4px solid #0d6efd !important;">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-info-circle me-2 fs-5"></i>
                        <div>
                            <strong class="d-block mb-1">Como usar:</strong>
                            <small class="d-block mb-1">• <strong>Duplo clique</strong> no canvas para adicionar nó</small>
                            <small class="d-block mb-1">• <strong>Duplo clique</strong> no nó para editar texto</small>
                            <small class="d-block mb-1">• <strong>Arraste</strong> para mover nós</small>
                            <small class="d-block">• <strong>Botão direito</strong> para excluir nó</small>
                        </div>
                    </div>
                </div>
                <div id="mindmap-container">
                    <canvas id="mindmap-canvas"></canvas>
                    <div class="zoom-indicator" id="zoom-indicator" style="display: none;">
                        <i class="bi bi-zoom-in me-1"></i><span id="zoom-value">100%</span>
                    </div>
                </div>
                <div class="mindmap-controls">
                    <button type="button" class="btn btn-sm btn-primary" id="btn-adicionar-no-mapa" title="Adicionar Nó">
                        <i class="bi bi-plus-circle me-1"></i><span>Adicionar</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" id="btn-limpar-mapa" title="Limpar">
                        <i class="bi bi-arrow-counterclockwise me-1"></i><span>Limpar</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="btn-salvar-mapa-toolbar" title="Salvar">
                        <i class="bi bi-save me-1"></i><span>Salvar</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-salvar-mapa-footer">
                    <i class="bi bi-save me-2"></i>Salvar Mapa Mental
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizar Nota -->
<div class="modal fade" id="modalVisualizarNota" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="background: #111114;">
            <div class="modal-header border-0 p-4 pb-0">
                <div class="d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 rounded-3 p-2 me-3">
                        <i class="bi bi-journal-text text-danger fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="view-nota-titulo-header">Título</h5>
                        <small class="text-muted" id="view-nota-data-header"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="badge rounded-pill bg-dark border border-secondary border-opacity-25 px-3 py-2 text-white-50" id="view-nota-categoria"></span>
                    <span class="badge rounded-pill bg-dark border border-secondary border-opacity-25 px-3 py-2 text-white-50" id="view-nota-curso"></span>
                    <span class="badge rounded-pill bg-dark border border-secondary border-opacity-25 px-3 py-2 text-white-50" id="view-nota-prioridade"></span>
                </div>
                
                <div class="bg-dark bg-opacity-40 p-4 rounded-4 border border-secondary border-opacity-10 text-white-50 shadow-inner" id="view-nota-conteudo" style="min-height: 250px; line-height: 1.8; overflow-y: auto; max-height: 400px; white-space: pre-wrap;">
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <div class="me-auto d-flex gap-2">
                    <button class="btn btn-outline-warning btn-sm border-0 bg-warning bg-opacity-10" id="btn-ia-resumir-view">
                        <i class="bi bi-stars me-1"></i>IA Resumo
                    </button>
                    <button class="btn btn-outline-success btn-sm border-0 bg-success bg-opacity-10" id="btn-ia-mapa-view">
                        <i class="bi bi-diagram-3 me-1"></i>IA Mapa
                    </button>
                </div>
                <button type="button" class="btn btn-link text-danger text-decoration-none me-2" id="btn-excluir-from-view">
                    <i class="bi bi-trash me-1"></i>Apagar
                </button>
                <button type="button" class="btn btn-dark border border-secondary border-opacity-25" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-danger px-4" id="btn-edit-from-view">
                    <i class="bi bi-pencil me-2"></i>Editar
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
                <div id="mindmap-visualizar-container" style="width: 100%; height: 600px; min-height: 400px; border: 2px solid var(--border-color, #333); border-radius: 16px; background: linear-gradient(135deg, #1a1a1a 0%, #2d1b1b 100%); position: relative; overflow: hidden; cursor: grab;">
                    <!-- Canvas será criado dinamicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Sistema de Mapa Mental Customizado com Canvas (100% compatível)
class MindMap {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        
        this.ctx = this.canvas.getContext('2d');
        this.nodes = [];
        this.edges = [];
        this.selectedNode = null;
        this.dragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.offset = { x: 0, y: 0 };
        this.scale = 1;
        this.nodeIdCounter = 1;
        this.hoveredNode = null;
        this.animationFrame = null;
        this.lastZoomTime = 0;
        
        // Cores modernas
        this.colors = {
            central: { bg: '#dc3545', border: '#c82333', text: '#fff' },
            node: { bg: '#6c757d', border: '#5a6268', text: '#fff' },
            hover: { bg: '#ff6b6b', border: '#ff5252', text: '#fff' },
            edge: '#dc3545',
            edgeHover: '#ff6b6b'
        };
        
        // Aguardar um pouco para garantir que o canvas está no DOM
        setTimeout(() => {
            this.setupCanvas();
            this.setupEvents();
            
            // Só adicionar nó padrão se não houver nós (para não sobrescrever dados carregados)
            if (this.nodes.length === 0) {
                this.addDefaultNode();
            }
            
            this.startAnimation();
        }, 100);
    }
    
    startAnimation() {
        // Parar animação anterior se existir
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
        }
        
        const animate = () => {
            if (this.canvas && this.ctx) {
                this.draw();
            }
            this.animationFrame = requestAnimationFrame(animate);
        };
        animate();
    }
    
    stopAnimation() {
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
            this.animationFrame = null;
        }
        
        // Remover event listener de resize se existir
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
            this._resizeHandler = null;
        }
    }
    
    setupCanvas() {
        const container = this.canvas.parentElement;
        if (!container) {
            console.error('Container do canvas não encontrado');
            return;
        }
        
        const dpr = window.devicePixelRatio || 1;
        
        const resize = () => {
            const rect = container.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                this.canvas.width = rect.width * dpr;
                this.canvas.height = rect.height * dpr;
                this.canvas.style.width = rect.width + 'px';
                this.canvas.style.height = rect.height + 'px';
                
                // Resetar contexto após mudança de tamanho
                this.ctx = this.canvas.getContext('2d');
                this.ctx.scale(dpr, dpr);
                
                // Redesenhar se houver nós
                if (this.nodes.length > 0) {
                    this.draw();
                }
            }
        };
        
        // Aguardar um pouco para garantir que o container está renderizado
        setTimeout(() => {
            resize();
        }, 50);
        
        // Debounce resize para performance
        let resizeTimeout;
        const resizeHandler = () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(resize, 250);
        };
        window.addEventListener('resize', resizeHandler);
        
        // Armazenar handler para poder remover depois se necessário
        this._resizeHandler = resizeHandler;
    }
    
    setupEvents() {
        // Mouse events
        this.canvas.addEventListener('mousedown', (e) => this.onMouseDown(e));
        this.canvas.addEventListener('mousemove', (e) => this.onMouseMove(e));
        this.canvas.addEventListener('mouseup', (e) => this.onMouseUp(e));
        this.canvas.addEventListener('mouseleave', (e) => this.onMouseLeave(e));
        this.canvas.addEventListener('dblclick', (e) => this.onDoubleClick(e));
        this.canvas.addEventListener('contextmenu', (e) => this.onRightClick(e));
        this.canvas.addEventListener('wheel', (e) => this.onWheel(e), { passive: false });
        
        // Touch events para mobile
        this.canvas.addEventListener('touchstart', (e) => this.onTouchStart(e), { passive: false });
        this.canvas.addEventListener('touchmove', (e) => this.onTouchMove(e), { passive: false });
        this.canvas.addEventListener('touchend', (e) => this.onTouchEnd(e));
    }
    
    addDefaultNode() {
        if (this.nodes.length === 0) {
            const dpr = window.devicePixelRatio || 1;
            let centerX, centerY;
            
            if (this.canvas && this.canvas.width && this.canvas.height) {
                centerX = (this.canvas.width / dpr) / 2;
                centerY = (this.canvas.height / dpr) / 2;
            } else {
                // Fallback para dimensões padrão
                const container = this.canvas ? this.canvas.parentElement : null;
                if (container) {
                    const rect = container.getBoundingClientRect();
                    centerX = (rect.width || 800) / 2;
                    centerY = (rect.height || 600) / 2;
                } else {
                    centerX = 400;
                    centerY = 300;
                }
            }
            
            this.addNode('Tema Central', centerX, centerY, true);
        }
    }
    
    addNode(text, x, y, isCentral = false) {
        console.log('addNode chamado - text:', text, 'x:', x, 'y:', y, 'isCentral:', isCentral);
        const node = {
            id: this.nodeIdCounter++,
            text: text || 'Novo Nó',
            x: x || 400,
            y: y || 300,
            width: 0, // Será calculado
            height: 50,
            isCentral: isCentral,
            color: isCentral ? this.colors.central.bg : this.colors.node.bg,
            borderColor: isCentral ? this.colors.central.border : this.colors.node.border,
            textColor: isCentral ? this.colors.central.text : this.colors.node.text,
            hover: false,
            pulse: 1 // Efeito de pulse ao criar
        };
        
        // Calcular largura baseada no texto
        if (this.ctx) {
            this.ctx.font = isCentral ? 'bold 16px Arial' : '14px Arial';
            const metrics = this.ctx.measureText(node.text);
            node.width = Math.max(120, metrics.width + 30);
        } else {
            // Fallback se ctx não estiver disponível
            node.width = Math.max(120, (node.text.length * 8) + 30);
            console.warn('Contexto do canvas não disponível, usando largura estimada');
        }
        
        this.nodes.push(node);
        console.log('Nó adicionado à lista. Total de nós:', this.nodes.length, 'Nó:', node);
        
        // Animação de entrada
        setTimeout(() => {
            if (node.pulse !== undefined) {
                node.pulse = 0;
            }
        }, 300);
        
        return node;
    }
    
    removeNode(nodeId) {
        this.nodes = this.nodes.filter(n => n.id !== nodeId);
        this.edges = this.edges.filter(e => e.from !== nodeId && e.to !== nodeId);
        this.draw();
    }
    
    addEdge(fromId, toId) {
        // Verificar se já existe
        if (this.edges.some(e => e.from === fromId && e.to === toId)) {
            return;
        }
        this.edges.push({ from: fromId, to: toId });
        this.draw();
    }
    
    getNodeAt(x, y) {
        // Ajustar coordenadas para o offset e scale
        const adjustedX = (x - this.offset.x) / this.scale;
        const adjustedY = (y - this.offset.y) / this.scale;
        
        for (let i = this.nodes.length - 1; i >= 0; i--) {
            const node = this.nodes[i];
            const nodeWidth = node.width * (node.hover ? 1.05 : 1);
            const nodeHeight = node.height * (node.hover ? 1.05 : 1);
            
            if (adjustedX >= node.x - nodeWidth/2 && adjustedX <= node.x + nodeWidth/2 &&
                adjustedY >= node.y - nodeHeight/2 && adjustedY <= node.y + nodeHeight/2) {
                return node;
            }
        }
        return null;
    }
    
    onMouseDown(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const node = this.getNodeAt(x, y);
        if (node) {
            this.selectedNode = node;
            this.dragging = true;
            const adjustedX = (x - this.offset.x) / this.scale;
            const adjustedY = (y - this.offset.y) / this.scale;
            this.dragOffset.x = adjustedX - node.x;
            this.dragOffset.y = adjustedY - node.y;
        } else {
            this.selectedNode = null;
        }
    }
    
    onMouseMove(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        if (this.dragging && this.selectedNode) {
            const adjustedX = (x - this.offset.x) / this.scale;
            const adjustedY = (y - this.offset.y) / this.scale;
            this.selectedNode.x = adjustedX - this.dragOffset.x;
            this.selectedNode.y = adjustedY - this.dragOffset.y;
        } else {
            // Verificar hover
            const node = this.getNodeAt(x, y);
            if (node !== this.hoveredNode) {
                if (this.hoveredNode) {
                    this.hoveredNode.hover = false;
                }
                this.hoveredNode = node;
                if (node) {
                    node.hover = true;
                    this.canvas.style.cursor = 'pointer';
                } else {
                    this.canvas.style.cursor = 'grab';
                }
            }
        }
    }
    
    onMouseUp(e) {
        this.dragging = false;
        this.canvas.style.cursor = 'grab';
    }
    
    onMouseLeave(e) {
        if (this.hoveredNode) {
            this.hoveredNode.hover = false;
            this.hoveredNode = null;
        }
        this.dragging = false;
    }
    
    // Touch events para mobile
    onTouchStart(e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            const touch = e.touches[0];
            const rect = this.canvas.getBoundingClientRect();
            const x = touch.clientX - rect.left;
            const y = touch.clientY - rect.top;
            
            const node = this.getNodeAt(x, y);
            if (node) {
                this.selectedNode = node;
                this.dragging = true;
                const adjustedX = (x - this.offset.x) / this.scale;
                const adjustedY = (y - this.offset.y) / this.scale;
                this.dragOffset.x = adjustedX - node.x;
                this.dragOffset.y = adjustedY - node.y;
            }
        }
    }
    
    onTouchMove(e) {
        e.preventDefault();
        if (this.dragging && this.selectedNode && e.touches.length === 1) {
            const touch = e.touches[0];
            const rect = this.canvas.getBoundingClientRect();
            const x = touch.clientX - rect.left;
            const y = touch.clientY - rect.top;
            const adjustedX = (x - this.offset.x) / this.scale;
            const adjustedY = (y - this.offset.y) / this.scale;
            
            this.selectedNode.x = adjustedX - this.dragOffset.x;
            this.selectedNode.y = adjustedY - this.dragOffset.y;
        }
    }
    
    onTouchEnd(e) {
        this.dragging = false;
    }
    
    onDoubleClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const node = this.getNodeAt(x, y);
        const adjustedX = (x - this.offset.x) / this.scale;
        const adjustedY = (y - this.offset.y) / this.scale;
        
        if (node) {
            // Editar nó existente
            this.editarNo(node);
        } else {
            // Adicionar novo nó
            this.adicionarNo(adjustedX, adjustedY);
        }
    }
    
    editarNo(node) {
        console.log('editarNo chamado para nó:', node);
        // Guardar referência do this
        const self = this;
        const textoAtual = node.text || '';
        
        // Usar prompt nativo diretamente - mais confiável dentro de modais do Bootstrap
        // O SweetAlert2 pode ter problemas de z-index e foco dentro de modais
        const novoTexto = prompt('Editar texto do nó:\n\n(Deixe vazio para cancelar)', textoAtual);
        
        if (novoTexto !== null) {
            const textoLimpo = novoTexto.trim();
            if (textoLimpo && textoLimpo !== textoAtual) {
                console.log('Atualizando texto do nó de "' + textoAtual + '" para "' + textoLimpo + '"');
                node.text = textoLimpo;
                // Recalcular largura
                if (self.ctx) {
                    self.ctx.font = node.isCentral ? 'bold 16px Arial' : '14px Arial';
                    const metrics = self.ctx.measureText(node.text);
                    node.width = Math.max(120, metrics.width + 30);
                } else {
                    node.width = Math.max(120, (node.text.length * 8) + 30);
                }
                console.log('Nó atualizado:', node);
                // Forçar redesenho
                if (self.draw) {
                    self.draw();
                }
                if (typeof showToast === 'function') {
                    showToast('Sucesso!', 'Nó editado com sucesso!', false);
                }
            } else if (textoLimpo === '') {
                console.log('Edição cancelada - texto vazio');
            } else {
                console.log('Texto não alterado');
            }
        } else {
            console.log('Edição cancelada pelo usuário');
        }
    }
    
    
    adicionarNo(x, y) {
        console.log('adicionarNo chamado em x:', x, 'y:', y);
        // Guardar referência do this
        const self = this;
        
        // Usar prompt nativo diretamente - mais confiável dentro de modais do Bootstrap
        // O SweetAlert2 pode ter problemas de z-index e foco dentro de modais
        const texto = prompt('Digite o texto do novo nó:\n\n(Deixe vazio para cancelar)', 'Novo Nó');
        
        if (texto !== null) {
            const textoLimpo = texto.trim();
            if (textoLimpo) {
                console.log('Adicionando nó com texto:', textoLimpo, 'em x:', x, 'y:', y);
                const newNode = self.addNode(textoLimpo, x, y);
                console.log('Nó adicionado:', newNode);
                // Conectar ao nó central se existir
                const centralNode = self.nodes.find(n => n.isCentral);
                if (centralNode && centralNode.id !== newNode.id) {
                    self.addEdge(centralNode.id, newNode.id);
                    console.log('Conectado ao nó central:', centralNode.id);
                }
                // Forçar redesenho
                if (self.draw) {
                    self.draw();
                }
                if (typeof showToast === 'function') {
                    showToast('Sucesso!', 'Nó adicionado com sucesso!', false);
                }
            } else {
                console.log('Adição cancelada - texto vazio');
            }
        } else {
            console.log('Adição cancelada pelo usuário');
        }
    }
    
    onRightClick(e) {
        e.preventDefault();
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const node = this.getNodeAt(x, y);
        if (node && !node.isCentral) {
            if (confirm('Deseja excluir este nó?')) {
                this.removeNode(node.id);
            }
        }
    }
    
    onWheel(e) {
        e.preventDefault();
        const now = Date.now();
        if (now - this.lastZoomTime < 50) return; // Throttle zoom
        this.lastZoomTime = now;
        
        const rect = this.canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        
        const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
        const newScale = Math.max(0.5, Math.min(2, this.scale * zoomFactor));
        
        // Zoom em direção ao mouse
        const scaleChange = newScale / this.scale;
        this.offset.x = mouseX - (mouseX - this.offset.x) * scaleChange;
        this.offset.y = mouseY - (mouseY - this.offset.y) * scaleChange;
        
        this.scale = newScale;
        
        // Atualizar indicador de zoom
        const zoomIndicator = document.getElementById('zoom-indicator');
        const zoomValue = document.getElementById('zoom-value');
        if (zoomIndicator && zoomValue) {
            zoomIndicator.style.display = 'block';
            zoomValue.textContent = Math.round(this.scale * 100) + '%';
            setTimeout(() => {
                zoomIndicator.style.display = 'none';
            }, 2000);
        }
    }
    
    draw() {
        const dpr = window.devicePixelRatio || 1;
        const width = this.canvas.width / dpr;
        const height = this.canvas.height / dpr;
        
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.save();
        this.ctx.translate(this.offset.x, this.offset.y);
        this.ctx.scale(this.scale, this.scale);
        
        // Grid de fundo sutil
        this.drawGrid(width, height);
        
        // Desenhar arestas
        this.edges.forEach(edge => {
            const fromNode = this.nodes.find(n => n.id === edge.from);
            const toNode = this.nodes.find(n => n.id === edge.to);
            if (fromNode && toNode) {
                this.drawEdge(fromNode, toNode);
            }
        });
        
        // Desenhar nós (central primeiro)
        const centralNodes = this.nodes.filter(n => n.isCentral);
        const otherNodes = this.nodes.filter(n => !n.isCentral);
        [...centralNodes, ...otherNodes].forEach(node => {
            this.drawNode(node);
        });
        
        this.ctx.restore();
    }
    
    drawGrid(width, height) {
        this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.03)';
        this.ctx.lineWidth = 1;
        
        const gridSize = 50;
        const startX = -this.offset.x % gridSize;
        const startY = -this.offset.y % gridSize;
        
        for (let x = startX; x < width; x += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(x, 0);
            this.ctx.lineTo(x, height);
            this.ctx.stroke();
        }
        
        for (let y = startY; y < height; y += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(0, y);
            this.ctx.lineTo(width, y);
            this.ctx.stroke();
        }
    }
    
    drawEdge(fromNode, toNode) {
        const isHovered = fromNode.hover || toNode.hover;
        const edgeColor = isHovered ? this.colors.edgeHover : this.colors.edge;
        
        // Gradiente para a linha
        const gradient = this.ctx.createLinearGradient(
            fromNode.x, fromNode.y,
            toNode.x, toNode.y
        );
        gradient.addColorStop(0, edgeColor);
        gradient.addColorStop(1, edgeColor + '80');
        
        this.ctx.strokeStyle = gradient;
        this.ctx.lineWidth = isHovered ? 3 : 2;
        this.ctx.shadowBlur = isHovered ? 8 : 4;
        this.ctx.shadowColor = edgeColor + '40';
        
        // Linha curva suave
        this.ctx.beginPath();
        const midX = (fromNode.x + toNode.x) / 2;
        const midY = (fromNode.y + toNode.y) / 2;
        const cpX = midX + (toNode.y - fromNode.y) * 0.2;
        const cpY = midY - (toNode.x - fromNode.x) * 0.2;
        
        this.ctx.moveTo(fromNode.x, fromNode.y);
        this.ctx.quadraticCurveTo(cpX, cpY, toNode.x, toNode.y);
        this.ctx.stroke();
        
        // Seta moderna
        const angle = Math.atan2(toNode.y - cpY, toNode.x - cpX);
        const arrowLength = 12;
        const arrowAngle = Math.PI / 6;
        const arrowX = toNode.x - Math.cos(angle) * (toNode.width / 2 + 5);
        const arrowY = toNode.y - Math.sin(angle) * (toNode.height / 2 + 5);
        
        this.ctx.beginPath();
        this.ctx.moveTo(arrowX, arrowY);
        this.ctx.lineTo(
            arrowX - arrowLength * Math.cos(angle - arrowAngle),
            arrowY - arrowLength * Math.sin(angle - arrowAngle)
        );
        this.ctx.lineTo(
            arrowX - arrowLength * Math.cos(angle + arrowAngle),
            arrowY - arrowLength * Math.sin(angle + arrowAngle)
        );
        this.ctx.closePath();
        this.ctx.fillStyle = edgeColor;
        this.ctx.fill();
        
        this.ctx.shadowBlur = 0;
    }
    
    drawNode(node) {
        const scale = node.hover ? 1.05 : (1 + node.pulse * 0.1);
        const currentWidth = node.width * scale;
        const currentHeight = node.height * scale;
        
        // Sombra moderna
        this.ctx.shadowColor = node.hover ? 'rgba(229, 9, 20, 0.4)' : 'rgba(0, 0, 0, 0.3)';
        this.ctx.shadowBlur = node.hover ? 15 : 8;
        this.ctx.shadowOffsetX = 0;
        this.ctx.shadowOffsetY = node.hover ? 4 : 2;
        
        // Gradiente de fundo
        const gradient = this.ctx.createLinearGradient(
            node.x - currentWidth / 2, node.y - currentHeight / 2,
            node.x + currentWidth / 2, node.y + currentHeight / 2
        );
        
        if (node.hover) {
            gradient.addColorStop(0, this.colors.hover.bg);
            gradient.addColorStop(1, this.colors.hover.bg + 'dd');
        } else {
            gradient.addColorStop(0, node.color);
            gradient.addColorStop(1, node.color + 'dd');
        }
        
        this.ctx.fillStyle = gradient;
        this.ctx.strokeStyle = node.hover ? this.colors.hover.border : node.borderColor;
        this.ctx.lineWidth = node.hover ? 3 : 2;
        
        // Retângulo arredondado (compatível com todos navegadores)
        const radius = 12;
        const rectX = node.x - currentWidth / 2;
        const rectY = node.y - currentHeight / 2;
        const w = currentWidth;
        const h = currentHeight;
        
        this.ctx.beginPath();
        if (this.ctx.roundRect) {
            // Método moderno (suportado em navegadores recentes)
            this.ctx.roundRect(rectX, rectY, w, h, radius);
        } else {
            // Fallback para navegadores antigos
            this.ctx.moveTo(rectX + radius, rectY);
            this.ctx.lineTo(rectX + w - radius, rectY);
            this.ctx.quadraticCurveTo(rectX + w, rectY, rectX + w, rectY + radius);
            this.ctx.lineTo(rectX + w, rectY + h - radius);
            this.ctx.quadraticCurveTo(rectX + w, rectY + h, rectX + w - radius, rectY + h);
            this.ctx.lineTo(rectX + radius, rectY + h);
            this.ctx.quadraticCurveTo(rectX, rectY + h, rectX, rectY + h - radius);
            this.ctx.lineTo(rectX, rectY + radius);
            this.ctx.quadraticCurveTo(rectX, rectY, rectX + radius, rectY);
            this.ctx.closePath();
        }
        this.ctx.fill();
        this.ctx.stroke();
        
        // Resetar sombra
        this.ctx.shadowColor = 'transparent';
        this.ctx.shadowBlur = 0;
        
        // Texto com sombra
        this.ctx.fillStyle = node.hover ? this.colors.hover.text : node.textColor;
        this.ctx.font = node.isCentral ? 'bold 16px Arial' : '14px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'middle';
        
        // Sombra no texto para melhor legibilidade
        this.ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
        this.ctx.shadowBlur = 2;
        this.ctx.shadowOffsetX = 1;
        this.ctx.shadowOffsetY = 1;
        
        // Quebrar texto se muito longo
        const maxWidth = currentWidth - 20;
        const textToDraw = node.text || 'Nó sem texto';
        const words = textToDraw.split(' ');
        let line = '';
        let textY = node.y - (words.length > 1 ? 8 : 0);
        
        for (let i = 0; i < words.length; i++) {
            const testLine = line + words[i] + ' ';
            const metrics = this.ctx.measureText(testLine);
            if (metrics.width > maxWidth && i > 0) {
                this.ctx.fillText(line.trim(), node.x, textY);
                line = words[i] + ' ';
                textY += 18;
            } else {
                line = testLine;
            }
        }
        if (line.trim()) {
            this.ctx.fillText(line.trim(), node.x, textY);
        }
        
        this.ctx.shadowColor = 'transparent';
        this.ctx.shadowBlur = 0;
        
        // Reduzir pulse
        if (node.pulse > 0) {
            node.pulse *= 0.9;
        }
    }
    
    getData() {
        return {
            nodes: this.nodes.map(n => ({
                id: n.id,
                text: n.text,
                x: n.x,
                y: n.y,
                isCentral: n.isCentral,
                color: n.color
            })),
            edges: this.edges
        };
    }
    
    loadData(data) {
        console.log('loadData chamado com:', data);
        
        if (!data) {
            console.log('Dados vazios, adicionando nó padrão');
            this.addDefaultNode();
            return;
        }
        
        // Se dados vierem como string JSON, parsear
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
                console.log('Dados parseados de string:', data);
            } catch (e) {
                console.error('Erro ao parsear dados string:', e, 'Dados:', data);
                this.addDefaultNode();
                return;
            }
        }
        
        // Validar estrutura de dados
        if (!data || typeof data !== 'object') {
            console.error('Dados inválidos:', data);
            this.addDefaultNode();
            return;
        }
        
        // Converter dados do formato antigo (vis-network) para o novo formato (Canvas)
        if (data.nodes && Array.isArray(data.nodes)) {
            console.log('Carregando', data.nodes.length, 'nós');
            this.nodes = [];
            
            const dpr = window.devicePixelRatio || 1;
            const canvasWidth = (this.canvas.width / dpr) || 800;
            const canvasHeight = (this.canvas.height / dpr) || 600;
            
            data.nodes.forEach((n, index) => {
                let node;
                
                // Se for formato antigo (vis-network) - tem 'label' mas não tem 'text'
                if (n.label && !n.text) {
                    node = {
                        id: n.id || (index + 1),
                        text: n.label || 'Nó',
                        x: (n.x !== undefined && n.x !== null) ? n.x : (canvasWidth / 2 + (index * 100)),
                        y: (n.y !== undefined && n.y !== null) ? n.y : (canvasHeight / 2 + (index * 50)),
                        width: 0, // Será calculado
                        height: 50,
                        isCentral: n.id === 1 || index === 0 || (n.color && (n.color.background === '#dc3545' || n.color === '#dc3545')),
                        color: (n.id === 1 || index === 0 || (n.color && (n.color.background === '#dc3545' || n.color === '#dc3545'))) ? this.colors.central.bg : this.colors.node.bg,
                        borderColor: (n.id === 1 || index === 0 || (n.color && (n.color.background === '#dc3545' || n.color === '#dc3545'))) ? this.colors.central.border : this.colors.node.border,
                        textColor: (n.id === 1 || index === 0 || (n.color && (n.color.background === '#dc3545' || n.color === '#dc3545'))) ? this.colors.central.text : this.colors.node.text,
                        hover: false,
                        pulse: 0
                    };
                } else {
                    // Formato novo (Canvas) ou já convertido
                    node = {
                        id: n.id || (index + 1),
                        text: n.text || n.label || 'Nó',
                        x: (n.x !== undefined && n.x !== null) ? n.x : (canvasWidth / 2 + (index * 100)),
                        y: (n.y !== undefined && n.y !== null) ? n.y : (canvasHeight / 2 + (index * 50)),
                        width: n.width || 0,
                        height: n.height || 50,
                        isCentral: n.isCentral || (index === 0 && n.isCentral !== false),
                        color: n.color || (n.isCentral ? this.colors.central.bg : this.colors.node.bg),
                        borderColor: n.borderColor || (n.isCentral ? this.colors.central.border : this.colors.node.border),
                        textColor: n.textColor || (n.isCentral ? this.colors.central.text : this.colors.node.text),
                        hover: false,
                        pulse: 0
                    };
                }
                
                // Garantir que pelo menos o primeiro nó seja central se não houver nenhum
                if (index === 0 && this.nodes.length === 0) {
                    node.isCentral = true;
                    node.color = this.colors.central.bg;
                    node.borderColor = this.colors.central.border;
                    node.textColor = this.colors.central.text;
                }
                
                this.nodes.push(node);
            });
            
            // Calcular larguras dos nós
            this.nodes.forEach(node => {
                if (!node.width || node.width === 0) {
                    this.ctx.font = node.isCentral ? 'bold 16px Arial' : '14px Arial';
                    const metrics = this.ctx.measureText(node.text || 'Nó');
                    node.width = Math.max(120, metrics.width + 30);
                }
            });
            
            this.edges = data.edges || [];
            if (this.nodes.length > 0) {
                const maxId = Math.max(...this.nodes.map(n => (n.id || 0)));
                this.nodeIdCounter = Math.max(maxId + 1, this.nodeIdCounter);
            }
            
            console.log('Mapa carregado:', this.nodes.length, 'nós,', this.edges.length, 'arestas');
        } else {
            console.log('Sem nodes, adicionando nó padrão');
            // Se não tiver nodes, criar nó central padrão
            this.addDefaultNode();
        }
    }
    
    clear() {
        this.nodes = [];
        this.edges = [];
        this.nodeIdCounter = 1;
        this.hoveredNode = null;
        this.selectedNode = null;
        this.addDefaultNode();
    }
    
    destroy() {
        this.stopAnimation();
        this.nodes = [];
        this.edges = [];
    }
}

// Variáveis globais
let mindMapInstance = null;
let mindMapVisualizar = null;

// ============================================
// FUNÇÕES GLOBAIS DO MAPA MENTAL (ANTES DO DOMContentLoaded)
// ============================================

// Função para adicionar nó no mapa mental
window.adicionarNoMapa = function() {
    console.log('adicionarNoMapa chamado, mindMapInstance:', mindMapInstance);
    
    if (!mindMapInstance) {
        console.error('Mapa mental não inicializado');
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Mapa mental não inicializado. Feche e abra o modal novamente.', true);
        } else {
            alert('Mapa mental não inicializado. Feche e abra o modal novamente.');
        }
        return;
    }
    
    if (!mindMapInstance.canvas) {
        console.error('Canvas não encontrado');
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Canvas do mapa mental não encontrado', true);
        }
        return;
    }
    
    const dpr = window.devicePixelRatio || 1;
    const canvasWidth = mindMapInstance.canvas.width / dpr;
    const canvasHeight = mindMapInstance.canvas.height / dpr;
    const centralNode = mindMapInstance.nodes.find(n => n.isCentral);
    
    let nodeX, nodeY;
    if (centralNode) {
        // Posicionar ao lado do nó central
        nodeX = centralNode.x + 180;
        nodeY = centralNode.y;
    } else {
        // Se não houver nó central, posicionar no centro
        nodeX = canvasWidth / 2 + 150;
        nodeY = canvasHeight / 2;
    }
    
    console.log('Adicionando nó em:', nodeX, nodeY);
    
    // Usar método do MindMap para adicionar nó
    try {
        mindMapInstance.adicionarNo(nodeX, nodeY);
    } catch (error) {
        console.error('Erro ao adicionar nó:', error);
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Erro ao adicionar nó: ' + error.message, true);
        } else {
            alert('Erro ao adicionar nó: ' + error.message);
        }
    }
};

// Função para limpar mapa mental
window.limparMapa = function() {
    if (!confirm('Deseja limpar o mapa mental? Esta ação não pode ser desfeita.')) return;
    
    if (mindMapInstance) {
        mindMapInstance.clear();
    }
};

// Função para salvar mapa mental
window.salvarMapaMental = function() {
    const tituloInput = document.getElementById('mapa-titulo');
    if (!tituloInput) {
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Campo de título não encontrado', true);
        } else {
            alert('Campo de título não encontrado');
        }
        return;
    }
    
    const titulo = tituloInput.value.trim();
    if (!titulo) {
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Título é obrigatório', true);
        } else {
            alert('Título é obrigatório');
        }
        tituloInput.focus();
        return;
    }
    
    if (!mindMapInstance || mindMapInstance.nodes.length === 0) {
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Adicione pelo menos um nó ao mapa', true);
        } else {
            alert('Adicione pelo menos um nó ao mapa');
        }
        return;
    }
    
    const dados = mindMapInstance.getData();
    
    // Mostrar loading
    const btnSalvar = document.querySelector('#modalNovoMapa .btn-danger');
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
    }
    
    fetch('salvar_mapa_mental.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ titulo: titulo, dados: JSON.stringify(dados) })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Sucesso!', 'Mapa mental salvo com sucesso!');
            }
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovoMapa'));
            if (modal) modal.hide();
            
            // Limpar e resetar
            tituloInput.value = '';
            if (mindMapInstance) {
                mindMapInstance.clear();
            }
            
            // Recarregar lista se estiver na aba de mapas
            const mapaTab = document.getElementById('mapa-tab');
            if (mapaTab && mapaTab.classList.contains('active')) {
                setTimeout(() => {
                    if (typeof carregarMapasMentais === 'function') {
                        carregarMapasMentais();
                    } else if (window.carregarMapasMentais) {
                        window.carregarMapasMentais();
                    }
                }, 500);
            }
        } else {
            if (typeof showToast === 'function') {
                showToast('Erro!', data.message || 'Erro ao salvar mapa mental', true);
            } else {
                alert(data.message || 'Erro ao salvar mapa mental');
            }
            if (btnSalvar) {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Mapa Mental';
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Erro de conexão: ' + error.message, true);
        } else {
            alert('Erro de conexão: ' + error.message);
        }
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="bi bi-check-lg me-2"></i>Salvar Mapa Mental';
        }
    });
};

// Função para visualizar mapa mental
window.visualizarMapa = function(id, titulo, dadosJson) {
    try {
        let dados;
        
        // Tentar parsear os dados
        if (typeof dadosJson === 'string') {
            try {
                dados = JSON.parse(dadosJson);
            } catch (e) {
                // Se falhar, tentar parsear novamente (pode estar duplamente encodado)
                try {
                    const parsed = JSON.parse(dadosJson);
                    dados = typeof parsed === 'string' ? JSON.parse(parsed) : parsed;
                } catch (e2) {
                    console.error('Erro ao parsear dados:', e2, 'Dados recebidos:', dadosJson);
                    if (typeof showToast === 'function') {
                        showToast('Erro!', 'Formato de dados inválido. Tente recriar o mapa.', true);
                    } else {
                        alert('Formato de dados inválido. Tente recriar o mapa.');
                    }
                    return;
                }
            }
        } else {
            dados = dadosJson;
        }
        
        // Validar dados
        if (!dados) {
            console.error('Dados nulos ou indefinidos');
            if (typeof showToast === 'function') {
                showToast('Erro!', 'Dados do mapa mental inválidos', true);
            } else {
                alert('Dados do mapa mental inválidos');
            }
            return;
        }
        
        // Se dados não tiver estrutura esperada, tentar converter
        if (!dados.nodes) {
            // Pode ser que os dados estejam em formato diferente
            if (Array.isArray(dados)) {
                dados = { nodes: dados, edges: [] };
            } else if (dados.data) {
                dados = dados.data;
            } else {
                console.error('Dados inválidos:', dados);
                if (typeof showToast === 'function') {
                    showToast('Erro!', 'Formato de dados não reconhecido', true);
                } else {
                    alert('Formato de dados não reconhecido');
                }
                return;
            }
        }
        
        // Garantir que nodes seja array
        if (!Array.isArray(dados.nodes)) {
            console.error('Nodes não é array:', dados.nodes);
            if (typeof showToast === 'function') {
                showToast('Erro!', 'Estrutura de dados inválida', true);
            } else {
                alert('Estrutura de dados inválida');
            }
            return;
        }
        
        const tituloElement = document.getElementById('mapa-visualizar-titulo');
        if (tituloElement) {
            tituloElement.innerHTML = '<i class="bi bi-diagram-3 me-2"></i>' + (typeof escapeHTML === 'function' ? escapeHTML(titulo) : titulo);
        }
        
        const container = document.getElementById('mindmap-visualizar-container');
        if (!container) {
            if (typeof showToast === 'function') {
                showToast('Erro!', 'Container de visualização não encontrado', true);
            } else {
                alert('Container de visualização não encontrado');
            }
            return;
        }
        
        // Limpar container completamente
        container.innerHTML = '';
        
        // Criar novo canvas
        const canvas = document.createElement('canvas');
        canvas.id = 'mindmap-visualizar-canvas';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.display = 'block';
        container.appendChild(canvas);
        
        // Mostrar modal primeiro
        const modalElement = document.getElementById('modalVisualizarMapa');
        if (!modalElement) {
            console.error('Modal de visualização não encontrado');
            if (typeof showToast === 'function') {
                showToast('Erro!', 'Modal de visualização não encontrado', true);
            } else {
                alert('Modal de visualização não encontrado');
            }
            return;
        }
        
        const modal = new bootstrap.Modal(modalElement);
        
        // Aguardar modal ser exibido antes de inicializar o canvas
        modalElement.addEventListener('shown.bs.modal', function onModalShown() {
            // Remover o listener após ser executado
            modalElement.removeEventListener('shown.bs.modal', onModalShown);
            
            // Limpar instância anterior se existir
            if (mindMapVisualizar) {
                try {
                    mindMapVisualizar.stopAnimation();
                } catch (e) {
                    console.warn('Erro ao parar animação anterior:', e);
                }
                mindMapVisualizar = null;
            }
            
            // Aguardar um pouco para garantir que o canvas está renderizado e o modal está totalmente visível
            setTimeout(() => {
                const canvasElement = document.getElementById('mindmap-visualizar-canvas');
                if (!canvasElement) {
                    console.error('Canvas não encontrado após criar');
                    if (typeof showToast === 'function') {
                        showToast('Erro!', 'Canvas não encontrado', true);
                    }
                    return;
                }
                
                // Verificar se o canvas tem dimensões
                const containerRect = container.getBoundingClientRect();
                console.log('Container dimensions:', containerRect.width, 'x', containerRect.height);
                
                try {
                    console.log('Inicializando mapa visualização com dados:', dados);
                    mindMapVisualizar = new MindMap('mindmap-visualizar-canvas');
                    
                    if (mindMapVisualizar && mindMapVisualizar.canvas) {
                        console.log('Mapa de visualização inicializado, canvas:', mindMapVisualizar.canvas.width, 'x', mindMapVisualizar.canvas.height);
                        console.log('Carregando dados:', dados);
                        
                        // Carregar dados
                        mindMapVisualizar.loadData(dados);
                        
                        console.log('Dados carregados. Nodes:', mindMapVisualizar.nodes.length, 'Edges:', mindMapVisualizar.edges.length);
                        
                        // Forçar redraw
                        if (mindMapVisualizar.draw) {
                            mindMapVisualizar.draw();
                        }
                    } else {
                        console.error('Falha ao inicializar mapa de visualização - canvas não disponível');
                        if (typeof showToast === 'function') {
                            showToast('Erro!', 'Erro ao inicializar visualização do mapa', true);
                        } else {
                            alert('Erro ao inicializar visualização do mapa');
                        }
                    }
                } catch (error) {
                    console.error('Erro ao inicializar mapa:', error);
                    console.error('Stack trace:', error.stack);
                    if (typeof showToast === 'function') {
                        showToast('Erro!', 'Erro ao visualizar mapa: ' + error.message, true);
                    } else {
                        alert('Erro ao visualizar mapa: ' + error.message);
                    }
                }
            }, 400);
        }, { once: true });
        
        // Mostrar o modal
        modal.show();
        
        // Limpar ao fechar
        modalElement.addEventListener('hidden.bs.modal', function onModalHidden() {
            // Remover o listener após ser executado
            modalElement.removeEventListener('hidden.bs.modal', onModalHidden);
            
            if (mindMapVisualizar) {
                try {
                    mindMapVisualizar.stopAnimation();
                } catch (e) {
                    console.warn('Erro ao parar animação:', e);
                }
                mindMapVisualizar = null;
            }
            
            // Limpar container
            container.innerHTML = '';
        }, { once: true });
    } catch (error) {
        console.error('Erro ao visualizar mapa:', error);
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Erro ao carregar mapa mental: ' + error.message, true);
        } else {
            alert('Erro ao carregar mapa mental: ' + error.message);
        }
    }
};

// Função para excluir mapa mental
window.excluirMapa = function(id) {
    if (!confirm('Tem certeza que deseja excluir este mapa mental?')) return;
    
    fetch('excluir_mapa_mental.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Sucesso!', 'Mapa mental excluído com sucesso!');
            }
            setTimeout(() => window.location.reload(), 1000);
        } else {
            if (typeof showToast === 'function') {
                showToast('Erro!', data.message, true);
            } else {
                alert(data.message);
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        if (typeof showToast === 'function') {
            showToast('Erro!', 'Erro de conexão', true);
        } else {
            alert('Erro de conexão');
        }
    });
};

// Função para criar mapa mental a partir de nota
window.criarMapaMental = function(notaId) {
    fetch(`buscar_nota.php?id=${notaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.nota) {
                const nota = data.nota;
                const tituloInput = document.getElementById('mapa-titulo');
                if (tituloInput) {
                    tituloInput.value = nota.titulo + ' - Mapa Mental';
                }
                
                const modal = new bootstrap.Modal(document.getElementById('modalNovoMapa'));
                modal.show();
                
                // Aguardar modal abrir e inicializar mapa
                setTimeout(() => {
                    if (!mindMapInstance) {
                        mindMapInstance = new MindMap('mindmap-canvas');
                    }
                    
                    // Criar nós baseados no conteúdo
                    const palavras = nota.conteudo.split(/\s+/).filter(p => p.length > 3).slice(0, 8);
                    const centralNode = mindMapInstance.nodes.find(n => n.isCentral);
                    if (centralNode) {
                        centralNode.text = nota.titulo.substring(0, 20);
                    }
                    
                    palavras.forEach((palavra, index) => {
                        const palavraLimpa = palavra.replace(/[^\w\s]/g, '').substring(0, 15);
                        if (palavraLimpa.length > 0 && centralNode) {
                            const angle = (index / palavras.length) * Math.PI * 2;
                            const radius = 150;
                            const nodeX = centralNode.x + Math.cos(angle) * radius;
                            const nodeY = centralNode.y + Math.sin(angle) * radius;
                            
                            const newNode = mindMapInstance.addNode(palavraLimpa, nodeX, nodeY);
                            mindMapInstance.addEdge(centralNode.id, newNode.id);
                        }
                    });
                }, 300);
            } else {
                if (typeof showToast === 'function') {
                    showToast('Erro!', data.message || 'Nota não encontrada', true);
                } else {
                    alert(data.message || 'Nota não encontrada');
                }
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            if (typeof showToast === 'function') {
                showToast('Erro!', 'Erro ao buscar nota: ' + error.message, true);
            } else {
                alert('Erro ao buscar nota: ' + error.message);
            }
        });
};

document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 600, once: true });
    
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
    
    // Event listeners para botões do mapa mental (substituindo onclick inline)
    const btnAdicionarNoMapa = document.getElementById('btn-adicionar-no-mapa');
    if (btnAdicionarNoMapa) {
        btnAdicionarNoMapa.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.adicionarNoMapa === 'function') {
                window.adicionarNoMapa();
            } else {
                console.error('Função adicionarNoMapa não encontrada');
                alert('Erro: Função não carregada. Recarregue a página.');
            }
        });
    }
    
    const btnLimparMapa = document.getElementById('btn-limpar-mapa');
    if (btnLimparMapa) {
        btnLimparMapa.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.limparMapa === 'function') {
                window.limparMapa();
            } else {
                console.error('Função limparMapa não encontrada');
                alert('Erro: Função não carregada. Recarregue a página.');
            }
        });
    }
    
    const btnSalvarMapaToolbar = document.getElementById('btn-salvar-mapa-toolbar');
    if (btnSalvarMapaToolbar) {
        btnSalvarMapaToolbar.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.salvarMapaMental === 'function') {
                window.salvarMapaMental();
            } else {
                console.error('Função salvarMapaMental não encontrada');
                alert('Erro: Função não carregada. Recarregue a página.');
            }
        });
    }
    
    const btnSalvarMapaFooter = document.getElementById('btn-salvar-mapa-footer');
    if (btnSalvarMapaFooter) {
        btnSalvarMapaFooter.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.salvarMapaMental === 'function') {
                window.salvarMapaMental();
            } else {
                console.error('Função salvarMapaMental não encontrada');
                alert('Erro: Função não carregada. Recarregue a página.');
            }
        });
    }
    
    // Inicializar mapa mental quando modal abrir
    const modalNovoMapa = document.getElementById('modalNovoMapa');
    if (modalNovoMapa) {
        modalNovoMapa.addEventListener('shown.bs.modal', function() {
            // Aguardar um pouco para garantir que o canvas está renderizado
            setTimeout(() => {
                const canvas = document.getElementById('mindmap-canvas');
                if (canvas && !mindMapInstance) {
                    console.log('Inicializando mapa mental...');
                    mindMapInstance = new MindMap('mindmap-canvas');
                    if (mindMapInstance && mindMapInstance.canvas) {
                        console.log('Mapa mental inicializado com sucesso');
                    } else {
                        console.error('Falha ao inicializar mapa mental');
                    }
                } else if (!canvas) {
                    console.error('Canvas não encontrado');
                } else {
                    console.log('Mapa mental já inicializado');
                }
            }, 300);
        });
        
        modalNovoMapa.addEventListener('hidden.bs.modal', function() {
            const tituloInput = document.getElementById('mapa-titulo');
            if (tituloInput) tituloInput.value = '';
            if (mindMapInstance) {
                mindMapInstance.stopAnimation();
                mindMapInstance.clear();
                // Não destruir completamente, apenas limpar para poder reutilizar
            }
        });
    }
    
    // Carregar mapas mentais salvos
    window.carregarMapasMentais = function() {
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
                            container.innerHTML = data.mapas.map(mapa => {
                                // Escapar dados para uso seguro no onclick
                                const dadosEscapados = escapeHTML(JSON.stringify(mapa.dados));
                                const tituloEscapado = escapeHTML(mapa.titulo);
                                
                                return `
                                <div class="col-md-6 col-lg-4">
                                    <div class="card note-card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title fw-bold" style="color: var(--bs-body-color, #fff);">${tituloEscapado}</h6>
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-calendar me-1"></i>
                                                ${new Date(mapa.data_criacao).toLocaleDateString('pt-BR')}
                                            </p>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-primary btn-visualizar-mapa" data-mapa-id="${mapa.id}" data-mapa-titulo="${tituloEscapado.replace(/"/g, '&quot;')}">
                                                    <i class="bi bi-eye me-1"></i>Visualizar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger btn-excluir-mapa" data-mapa-id="${mapa.id}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            }).join('');
                        }
                        
                        // Adicionar event listeners para os botões recém-criados usando event delegation
                        // Usar event delegation no container para evitar problemas com elementos dinâmicos
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar mapas:', error);
            });
    };
    
    // Carregar mapas quando a aba for ativada
    const mapaTab = document.getElementById('mapa-tab');
    if (mapaTab) {
        mapaTab.addEventListener('shown.bs.tab', function() {
            if (typeof carregarMapasMentais === 'function') {
                carregarMapasMentais();
            } else if (window.carregarMapasMentais) {
                window.carregarMapasMentais();
            }
        });
    }
    
    // Event delegation para botões de visualizar e excluir mapa (funciona com elementos dinâmicos)
    const mapasListaContainer = document.getElementById('mapas-mentais-lista');
    if (mapasListaContainer) {
        mapasListaContainer.addEventListener('click', function(e) {
            // Visualizar mapa
            if (e.target.closest('.btn-visualizar-mapa')) {
                const btn = e.target.closest('.btn-visualizar-mapa');
                const id = parseInt(btn.getAttribute('data-mapa-id'));
                const titulo = btn.getAttribute('data-mapa-titulo');
                
                // Buscar dados do mapa via API
                fetch(`buscar_mapa_mental.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.mapa) {
                            if (typeof window.visualizarMapa === 'function') {
                                window.visualizarMapa(id, titulo, data.mapa.dados);
                            } else {
                                console.error('Função visualizarMapa não encontrada');
                                alert('Erro: Função não carregada. Recarregue a página.');
                            }
                        } else {
                            if (typeof showToast === 'function') {
                                showToast('Erro!', data.message || 'Mapa não encontrado', true);
                            } else {
                                alert(data.message || 'Mapa não encontrado');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar mapa:', error);
                        if (typeof showToast === 'function') {
                            showToast('Erro!', 'Erro ao carregar mapa mental', true);
                        } else {
                            alert('Erro ao carregar mapa mental');
                        }
                    });
            }
            
            // Excluir mapa
            if (e.target.closest('.btn-excluir-mapa')) {
                const btn = e.target.closest('.btn-excluir-mapa');
                const id = parseInt(btn.getAttribute('data-mapa-id'));
                if (typeof window.excluirMapa === 'function') {
                    window.excluirMapa(id);
                } else {
                    console.error('Função excluirMapa não encontrada');
                    alert('Erro: Função não carregada. Recarregue a página.');
                }
            }
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
    Swal.fire({
        title: 'Excluir Anotação?',
        text: "Essa ação não pode ser desfeita e você perderá todo o conteúdo desta nota.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e50914',
        cancelButtonColor: '#2a2a2e',
        confirmButtonText: '<i class="bi bi-trash me-2"></i>Sim, excluir',
        cancelButtonText: 'Cancelar',
        background: '#1a1a1e',
        color: '#fff',
        borderRadius: '20px',
        customClass: {
            confirmButton: 'btn btn-danger px-4 py-2',
            cancelButton: 'btn btn-secondary px-4 py-2'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Excluindo...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('excluir_nota_curso.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Excluída!',
                        text: 'Sua anotação foi removida com sucesso.',
                        icon: 'success',
                        background: '#1a1a1e',
                        color: '#fff',
                        confirmButtonColor: '#e50914'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Erro!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire('Erro!', 'Falha na conexão com o servidor.', 'error');
            });
        }
    });
}


// Funções de IA
async function resumirComIA(notaId) {
    Swal.fire({
        title: 'Gerando Resumo...',
        text: 'A IA está analisando sua nota.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('processar_ia_notas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'resumir', nota_id: notaId })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                title: 'Resumo da IA',
                html: `<div class="text-start" style="font-size: 0.95rem;">${data.resumo.replace(/\n/g, '<br>')}</div>`,
                icon: 'success',
                confirmButtonText: 'Que legal!',
                confirmButtonColor: '#e50914'
            });
        } else {
            Swal.fire('Erro!', data.message, 'error');
        }
    } catch (error) {
        Swal.fire('Erro!', 'Falha na conexão com a IA.', 'error');
    }
}

async function gerarMapaComIA(notaId) {
    Swal.fire({
        title: 'Gerando Mapa Mental...',
        text: 'A IA está estruturando os conceitos.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('processar_ia_notas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ acao: 'gerar_mapa', nota_id: notaId })
        });
        const data = await response.json();

        if (data.success && data.mapa) {
            Swal.close();
            const modal = new bootstrap.Modal(document.getElementById('modalNovoMapa'));
            const tituloInput = document.getElementById('mapa-titulo');
            if (tituloInput) tituloInput.value = 'Mapa IA - ' + notaId;
            modal.show();

            setTimeout(() => {
                if (!mindMapInstance) {
                    mindMapInstance = new MindMap('mindmap-canvas');
                }
                mindMapInstance.loadData(data.mapa);
                showToast('Sucesso!', 'Mapa gerado pela IA!');
            }, 500);
        } else {
            Swal.fire('Erro!', data.message, 'error');
        }
    } catch (error) {
        Swal.fire('Erro!', 'Falha na conexão com a IA.', 'error');
    }
}

function visualizarNota(id) {
    fetch(`buscar_nota.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.nota) {
                const nota = data.nota;
                document.getElementById('view-nota-titulo-header').textContent = nota.titulo;
                document.getElementById('view-nota-data-header').textContent = 'Criada em ' + new Date(nota.data_criacao).toLocaleDateString('pt-BR');
                document.getElementById('view-nota-categoria').textContent = nota.categoria.charAt(0).toUpperCase() + nota.categoria.slice(1);
                document.getElementById('view-nota-curso').textContent = nota.nome_curso || 'Sem curso';
                document.getElementById('view-nota-prioridade').textContent = 'Prioridade ' + nota.prioridade.charAt(0).toUpperCase() + nota.prioridade.slice(1);
                document.getElementById('view-nota-conteudo').innerHTML = nota.conteudo.replace(/\n/g, '<br>');
                
                // Configurar botões
                document.getElementById('btn-ia-resumir-view').onclick = () => resumirComIA(nota.id);
                document.getElementById('btn-ia-mapa-view').onclick = () => gerarMapaComIA(nota.id);
                document.getElementById('btn-excluir-from-view').onclick = () => {
                    if (confirm('Deseja realmente apagar esta nota?')) {
                        bootstrap.Modal.getInstance(document.getElementById('modalVisualizarNota')).hide();
                        excluirNota(nota.id);
                    }
                };
                document.getElementById('btn-edit-from-view').onclick = () => {
                    bootstrap.Modal.getInstance(document.getElementById('modalVisualizarNota')).hide();
                    editarNota(nota.id);
                };


                const modal = new bootstrap.Modal(document.getElementById('modalVisualizarNota'));
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


</script>

<?php
require_once 'templates/footer.php';
?>
