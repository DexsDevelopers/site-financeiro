<?php
// dashboard.php (Versão Otimizada)

date_default_timezone_set('America/Sao_Paulo');
require_once 'templates/header.php';
// A conexão com o banco ($pdo) e o ID do usuário ($userId) já vêm do header.php

$mes_selecionado = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$ano_selecionado = isset($_GET['ano']) ? (int)$_GET['ano'] : date('Y');
// Filtro de conta (carteira); 'all' para todas
$conta_param = $_GET['conta'] ?? 'all';
$conta_id = ($conta_param !== 'all') ? (int)$conta_param : 0;
setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'portuguese');
$data_base = DateTime::createFromFormat('Y-n-d', "$ano_selecionado-$mes_selecionado-1");
$nome_mes_ano = ucfirst(strftime('%B de %Y', $data_base->getTimestamp()));

// Inicializa todas as variáveis
$totalReceitas = 0; $totalDespesas = 0; $saldoMes = 0; $ultimos_lancamentos = [];
$barChartLabels = []; $barChartData = []; $pieChartLabels = []; $pieChartData = []; $pieChartColors = [];
$lista_categorias = [];
$lista_contas = [];
$saldoInicialContas = 0.0;

// Buscar tarefas para o dashboard
$tarefas_hoje = []; $tarefas_pendentes_resumo = []; $stats_tarefas = [];
try {
    // Contas do usuário para o filtro e formulários
    try {
        $stmt_contas = $pdo->prepare("SELECT id, nome FROM contas WHERE id_usuario = ? ORDER BY nome");
        $stmt_contas->execute([$userId]);
        $lista_contas = $stmt_contas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $lista_contas = [];
    }

    // Tarefas de hoje
    $stmt_hoje = $pdo->prepare("SELECT * FROM tarefas WHERE id_usuario = ? AND status = 'pendente' AND (DATE(data_limite) = CURDATE() OR data_limite IS NULL) ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa') LIMIT 5");
    $stmt_hoje->execute([$userId]);
    $tarefas_hoje = $stmt_hoje->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas de tarefas - CORRIGIDO PARA SINCRONIZAR COM TAREFAS.PHP
    // Tarefas de hoje
    $stmt_stats_hoje = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas FROM tarefas WHERE id_usuario = ? AND DATE(data_criacao) = CURDATE()");
    $stmt_stats_hoje->execute([$userId]);
    $stats_hoje = $stmt_stats_hoje->fetch();
    
    // Tarefas da semana
    $stmt_stats_semana = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluidas FROM tarefas WHERE id_usuario = ? AND YEARWEEK(data_criacao, 1) = YEARWEEK(CURDATE(), 1)");
    $stmt_stats_semana->execute([$userId]);
    $stats_semana = $stmt_stats_semana->fetch();
    
    $stmt_pendentes = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ? AND status = 'pendente'");
    $stmt_pendentes->execute([$userId]);
    $total_pendentes = $stmt_pendentes->fetchColumn();
    
    $stats_tarefas = [
        'hoje' => $stats_hoje,
        'semana' => $stats_semana,
        'pendentes' => $total_pendentes
    ];
} catch (PDOException $e) {
    $tarefas_hoje = [];
    $stats_hoje = ['total' => 0, 'concluidas' => 0];
    $stats_semana = ['total' => 0, 'concluidas' => 0];
    $total_pendentes = 0;
    $stats_tarefas = [
        'hoje' => $stats_hoje,
        'semana' => $stats_semana,
        'pendentes' => $total_pendentes
    ];
}

// Garantir que os valores são números
$stats_tarefas['hoje']['total'] = (int)($stats_tarefas['hoje']['total'] ?? 0);
$stats_tarefas['hoje']['concluidas'] = (int)($stats_tarefas['hoje']['concluidas'] ?? 0);
$stats_tarefas['semana']['total'] = (int)($stats_tarefas['semana']['total'] ?? 0);
$stats_tarefas['semana']['concluidas'] = (int)($stats_tarefas['semana']['concluidas'] ?? 0);
$stats_tarefas['pendentes'] = (int)($stats_tarefas['pendentes'] ?? 0);

function getPrioridadeBadge($prioridade) { 
    switch ($prioridade) { 
        case 'Alta': return 'bg-danger'; 
        case 'Média': return 'bg-warning text-dark'; 
        case 'Baixa': return 'bg-success'; 
        default: return 'bg-secondary'; 
    } 
}
// A lógica para $dias_de_uso foi removida daqui.

try {
    // Resumo financeiro com filtro de conta (se selecionada)
    $params_fin = [$userId, $mes_selecionado, $ano_selecionado];
    $sql_financeiro = "SELECT 
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as total_receitas, 
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as total_despesas 
        FROM transacoes 
        WHERE id_usuario = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?";
    if ($conta_id > 0) { $sql_financeiro .= " AND id_conta = ?"; $params_fin[] = $conta_id; }
    $stmt_financeiro = $pdo->prepare($sql_financeiro);
    $stmt_financeiro->execute($params_fin);
    $resumo = $stmt_financeiro->fetch();
    $totalReceitas = $resumo['total_receitas'] ?? 0;
    $totalDespesas = $resumo['total_despesas'] ?? 0;

    // Saldo inicial (conta selecionada ou todas)
    if ($conta_id > 0) {
        $stmt_si = $pdo->prepare("SELECT COALESCE(saldo_inicial,0) FROM contas WHERE id = ? AND id_usuario = ?");
        $stmt_si->execute([$conta_id, $userId]);
        $saldoInicialContas = (float)($stmt_si->fetchColumn() ?? 0);
    } else {
        $stmt_si = $pdo->prepare("SELECT COALESCE(SUM(saldo_inicial),0) FROM contas WHERE id_usuario = ?");
        $stmt_si->execute([$userId]);
        $saldoInicialContas = (float)($stmt_si->fetchColumn() ?? 0);
    }

    // Saldo do período considerando saldo inicial
    $saldoMes = $saldoInicialContas + ($totalReceitas - $totalDespesas);
    
    // NOVA LÓGICA: Busca as metas de compras para o modal
$metas_para_alocar = [];
if ($saldoMes > 0) {
    try {
        $stmt_metas = $pdo->prepare("SELECT id, nome_item, valor_total, valor_poupado FROM compras_futuras WHERE id_usuario = ? AND status = 'planejando' ORDER BY ordem ASC");
        $stmt_metas->execute([$userId]);
        $metas_para_alocar = $stmt_metas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignora o erro para não quebrar o dashboard
    }
}

    $sql_chart_bar = "SELECT DAY(data_transacao) as dia, SUM(valor) as total_gasto 
        FROM transacoes 
        WHERE id_usuario = ? AND tipo = 'despesa' AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?";
    $params_bar = [$userId, $mes_selecionado, $ano_selecionado];
    if ($conta_id > 0) { $sql_chart_bar .= " AND id_conta = ?"; $params_bar[] = $conta_id; }
    $sql_chart_bar .= " GROUP BY DAY(data_transacao) ORDER BY dia ASC";
    $stmt_chart_bar = $pdo->prepare($sql_chart_bar);
    $stmt_chart_bar->execute($params_bar);
    foreach ($stmt_chart_bar->fetchAll() as $dado) {
        $barChartLabels[] = 'Dia ' . $dado['dia'];
        $barChartData[] = $dado['total_gasto'];
    }
    
    

    $sql_chart_pie = "SELECT c.nome as categoria, SUM(t.valor) as total_categoria 
        FROM transacoes t 
        JOIN categorias c ON t.id_categoria = c.id 
        WHERE t.id_usuario = ? AND t.tipo = 'despesa' AND MONTH(t.data_transacao) = ? AND YEAR(t.data_transacao) = ?";
    $params_pie = [$userId, $mes_selecionado, $ano_selecionado];
    if ($conta_id > 0) { $sql_chart_pie .= " AND t.id_conta = ?"; $params_pie[] = $conta_id; }
    $sql_chart_pie .= " GROUP BY t.id_categoria, c.nome ORDER BY total_categoria DESC";
    $stmt_chart_pie = $pdo->prepare($sql_chart_pie);
    $stmt_chart_pie->execute($params_pie);
    $dados_pie_chart = $stmt_chart_pie->fetchAll();
    $cores_disponiveis = ['#e50914', '#f9a826', '#0984e3', '#00b894', '#6c5ce7', '#e84393', '#fd79a8'];
    $colorIndex = 0;
    foreach ($dados_pie_chart as $dado) { $pieChartLabels[] = $dado['categoria']; $pieChartData[] = $dado['total_categoria']; $pieChartColors[] = $cores_disponiveis[$colorIndex % count($cores_disponiveis)]; $colorIndex++; }

    $sql_ultimos = "SELECT t.id, t.descricao, t.valor, t.tipo, t.data_transacao, c.nome as nome_categoria 
        FROM transacoes t 
        LEFT JOIN categorias c ON t.id_categoria = c.id 
        WHERE t.id_usuario = ?";
    $params_ult = [$userId];
    if ($conta_id > 0) { $sql_ultimos .= " AND t.id_conta = ?"; $params_ult[] = $conta_id; }
    $sql_ultimos .= " ORDER BY t.data_transacao DESC, t.id DESC LIMIT 5";
    $stmt_ultimos = $pdo->prepare($sql_ultimos);
    $stmt_ultimos->execute($params_ult);
    $ultimos_lancamentos = $stmt_ultimos->fetchAll();
    
    $stmt_cats = $pdo->prepare("SELECT id, nome, tipo FROM categorias WHERE id_usuario = ? ORDER BY tipo, nome");
    $stmt_cats->execute([$userId]);
    $lista_categorias = $stmt_cats->fetchAll();

} catch (PDOException $e) {
    die("Erro ao buscar dados do dashboard: " . $e->getMessage());
}
?>



<!-- ========================================= -->
<!-- DASHBOARD MODERNO - HEADER + FILTROS -->
<!-- ========================================= -->
<div class="dashboard-header">
    <div class="dashboard-header-content">
        <div class="dashboard-title-section">
            <h1 class="dashboard-title">Dashboard</h1>
            <p class="dashboard-subtitle"><?php echo $nome_mes_ano; ?></p>
        </div>
        <div class="dashboard-actions">
            <button class="btn-toggle-saldo" id="btn-toggle-saldo" title="Mostrar/Ocultar valores">
                <i class="bi bi-eye-fill"></i>
            </button>
            <button class="btn-novo-lancamento" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">
                <i class="bi bi-plus-lg"></i>
                <span>Novo</span>
            </button>
        </div>
    </div>
    
    <form id="filtroMesAno" class="dashboard-filtros">
        <div class="filtro-group">
            <select name="mes" id="selectMes" class="filtro-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($m == $mes_selecionado) ? 'selected' : ''; ?>>
                        <?php echo ucfirst(strftime('%B', mktime(0, 0, 0, $m, 1))); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filtro-group">
            <select name="ano" id="selectAno" class="filtro-select">
                <?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): ?>
                    <option value="<?php echo $a; ?>" <?php echo ($a == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $a; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="filtro-group filtro-conta">
            <select name="conta" id="selectConta" class="filtro-select">
                <option value="all" <?php echo ($conta_id === 0) ? 'selected' : ''; ?>>Todas as contas</option>
                <?php foreach($lista_contas as $conta): ?>
                    <option value="<?php echo (int)$conta['id']; ?>" <?php echo ($conta_id === (int)$conta['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($conta['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<main class="dashboard-main">
    <!-- ========================================= -->
    <!-- CARDS KPI - RESUMO FINANCEIRO -->
    <!-- ========================================= -->
    <div class="kpi-grid">
        <div class="kpi-card kpi-receitas">
            <div class="kpi-icon">
                <i class="bi bi-arrow-up-circle-fill"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-label">Receitas</span>
                <span class="kpi-value valor-sensivel">R$ <?php echo number_format($totalReceitas, 2, ',', '.'); ?></span>
            </div>
            <div class="kpi-indicator"></div>
        </div>
        
        <div class="kpi-card kpi-despesas">
            <div class="kpi-icon">
                <i class="bi bi-arrow-down-circle-fill"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-label">Despesas</span>
                <span class="kpi-value valor-sensivel">R$ <?php echo number_format($totalDespesas, 2, ',', '.'); ?></span>
            </div>
            <div class="kpi-indicator"></div>
        </div>
        
        <div class="kpi-card kpi-saldo <?php echo ($saldoMes >= 0) ? 'positivo' : 'negativo'; ?>">
            <div class="kpi-icon">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-label">Saldo</span>
                <span class="kpi-value valor-sensivel">R$ <?php echo number_format($saldoMes, 2, ',', '.'); ?></span>
            </div>
            <div class="kpi-indicator"></div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- LANÇAMENTO RÁPIDO COM IA -->
    <!-- ========================================= -->
    <div class="ia-card">
        <div class="ia-header">
            <div class="ia-icon">
                <i class="bi bi-magic"></i>
            </div>
            <div class="ia-title">
                <h3>Lançamento Inteligente</h3>
                <p>Digite naturalmente e a IA categoriza automaticamente</p>
            </div>
        </div>
        <form id="formIaRapida" class="ia-form">
            <div class="ia-input-wrapper">
                <input type="text" id="inputIa" class="ia-input" placeholder="Ex: Comprei pizza por R$ 25 hoje" required>
                <button class="ia-submit" type="submit" id="btnIaSubmit">
                    <i class="bi bi-send-fill"></i>
                    <span>Lançar</span>
                </button>
            </div>
        </form>
    </div>
        
    <!-- ========================================= -->
    <!-- GRID PRINCIPAL - TAREFAS + PRODUTIVIDADE -->
    <!-- ========================================= -->
    <div class="dashboard-grid">
        <!-- TAREFAS DE HOJE -->
        <div class="dashboard-card tarefas-card">
            <div class="card-header-modern">
                <div class="card-header-left">
                    <div class="card-icon tarefas-icon">
                        <i class="bi bi-check2-square"></i>
                    </div>
                    <div>
                        <h3 class="card-title-modern">Tarefas de Hoje</h3>
                        <p class="card-subtitle-modern"><?php echo count($tarefas_hoje); ?> pendentes</p>
                    </div>
                </div>
                <a href="tarefas.php" class="btn-ver-mais">
                    Ver todas <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            
            <div class="card-body-modern">
                <?php if(empty($tarefas_hoje)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle-fill"></i>
                        <p>Nenhuma tarefa para hoje!</p>
                        <a href="tarefas.php" class="btn-add-tarefa">
                            <i class="bi bi-plus"></i> Adicionar
                        </a>
                    </div>
                <?php else: ?>
                    <div class="tarefas-lista">
                        <?php foreach($tarefas_hoje as $tarefa): ?>
                            <div class="tarefa-item" data-prioridade="<?php echo strtolower($tarefa['prioridade']); ?>">
                                <div class="tarefa-check">
                                    <input type="checkbox" class="task-checkbox" data-id="<?php echo $tarefa['id']; ?>">
                                </div>
                                <div class="tarefa-content">
                                    <span class="tarefa-titulo"><?php echo htmlspecialchars($tarefa['descricao']); ?></span>
                                    <div class="tarefa-meta">
                                        <span class="tarefa-prioridade <?php echo strtolower($tarefa['prioridade']); ?>">
                                            <?php echo $tarefa['prioridade']; ?>
                                        </span>
                                        <?php if($tarefa['data_limite']): ?>
                                            <span class="tarefa-data">
                                                <i class="bi bi-calendar3"></i>
                                                <?php echo date('d/m', strtotime($tarefa['data_limite'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if($tarefa['tempo_estimado'] > 0): ?>
                                            <span class="tarefa-tempo">
                                                <i class="bi bi-clock"></i>
                                                <?php 
                                                    $h = floor($tarefa['tempo_estimado'] / 60);
                                                    $m = $tarefa['tempo_estimado'] % 60;
                                                    echo ($h > 0 ? $h.'h ' : '') . ($m > 0 ? $m.'min' : '');
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button class="btn-timer btn-start-timer" data-id="<?php echo $tarefa['id']; ?>" title="Iniciar Timer">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- SIDEBAR DIREITA -->
        <div class="dashboard-sidebar">
            <!-- PRODUTIVIDADE -->
            <div class="dashboard-card produtividade-card">
                <div class="card-header-compact">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span>Produtividade</span>
                </div>
                <div class="produtividade-stats">
                    <div class="prod-stat">
                        <span class="prod-value verde"><?php echo $stats_tarefas['hoje']['concluidas']; ?></span>
                        <span class="prod-label">Hoje</span>
                    </div>
                    <div class="prod-stat">
                        <span class="prod-value azul"><?php echo $stats_tarefas['semana']['concluidas']; ?></span>
                        <span class="prod-label">Semana</span>
                    </div>
                    <div class="prod-stat">
                        <span class="prod-value amarelo"><?php echo $stats_tarefas['pendentes']; ?></span>
                        <span class="prod-label">Pendentes</span>
                    </div>
                </div>
                <?php 
                    $progresso_hoje = $stats_tarefas['hoje']['total'] > 0 ? 
                        ($stats_tarefas['hoje']['concluidas'] / $stats_tarefas['hoje']['total']) * 100 : 0;
                ?>
                <div class="progresso-wrapper">
                    <div class="progresso-bar">
                        <div class="progresso-fill" style="width: <?php echo $progresso_hoje; ?>%"></div>
                    </div>
                    <span class="progresso-text"><?php echo round($progresso_hoje); ?>% hoje</span>
                </div>
            </div>
            
            <!-- GRÁFICO PIZZA -->
            <div class="dashboard-card grafico-card">
                <div class="card-header-compact">
                    <i class="bi bi-pie-chart-fill"></i>
                    <span>Despesas por Categoria</span>
                </div>
                <div class="grafico-wrapper">
                    <?php if(empty($pieChartData)): ?>
                        <div class="empty-state small">
                            <i class="bi bi-pie-chart"></i>
                            <p>Sem despesas este mês</p>
                        </div>
                    <?php else: ?>
                        <canvas id="pieChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
        
    <!-- ========================================= -->
    <!-- GRÁFICO DE BARRAS - DESPESAS DIÁRIAS -->
    <!-- ========================================= -->
    <div class="dashboard-card grafico-barras-card">
        <div class="card-header-modern">
            <div class="card-header-left">
                <div class="card-icon barras-icon">
                    <i class="bi bi-bar-chart-fill"></i>
                </div>
                <div>
                    <h3 class="card-title-modern">Despesas Diárias</h3>
                    <p class="card-subtitle-modern"><?php echo ucfirst(strftime('%B', $data_base->getTimestamp())); ?></p>
                </div>
            </div>
        </div>
        <div class="card-body-modern">
            <div class="chart-container-modern">
                <canvas id="barChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- ÚLTIMOS LANÇAMENTOS -->
    <!-- ========================================= -->
    <div class="dashboard-card lancamentos-card">
        <div class="card-header-modern">
            <div class="card-header-left">
                <div class="card-icon lancamentos-icon">
                    <i class="bi bi-receipt"></i>
                </div>
                <div>
                    <h3 class="card-title-modern">Últimos Lançamentos</h3>
                    <p class="card-subtitle-modern"><?php echo count($ultimos_lancamentos); ?> mais recentes</p>
                </div>
            </div>
            <a href="extrato_completo.php" class="btn-ver-mais">
                Ver extrato <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="card-body-modern">
            <?php if(empty($ultimos_lancamentos)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Nenhum lançamento ainda</p>
                </div>
            <?php else: ?>
                <div class="lancamentos-lista">
                    <?php foreach ($ultimos_lancamentos as $lancamento): ?>
                        <div class="lancamento-item <?php echo $lancamento['tipo']; ?>">
                            <div class="lancamento-indicador"></div>
                            <div class="lancamento-content">
                                <div class="lancamento-principal">
                                    <span class="lancamento-descricao"><?php echo htmlspecialchars($lancamento['descricao']); ?></span>
                                    <span class="lancamento-valor valor-sensivel <?php echo $lancamento['tipo']; ?>">
                                        <?php echo ($lancamento['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($lancamento['valor'], 2, ',', '.'); ?>
                                    </span>
                                </div>
                                <div class="lancamento-detalhes">
                                    <span class="lancamento-categoria"><?php echo htmlspecialchars($lancamento['nome_categoria'] ?? 'Sem Categoria'); ?></span>
                                    <span class="lancamento-data">
                                        <i class="bi bi-calendar3"></i>
                                        <?php echo date('d/m/Y', strtotime($lancamento['data_transacao'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Novo Lançamento -->
<div class="modal fade" id="modalNovoLancamento" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Novo Lançamento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="salvar_transacao.php" method="POST" id="formNovoLancamento">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <input type="text" class="form-control" id="descricao" name="descricao" placeholder="Ex: Almoço, Salário" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valor" class="form-label">Valor (R$)</label>
                            <input type="number" class="form-control" id="valor" name="valor" step="0.01" min="0" placeholder="25,50" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_transacao" class="form-label">Data</label>
                            <input type="date" class="form-control" id="data_transacao" name="data_transacao" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="id_categoria" class="form-label">Categoria</label>
                        <select class="form-select" name="id_categoria" id="id_categoria" required>
                            <option value="">Selecione uma categoria</option>
                            <optgroup label="Despesas">
                                <?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'despesa'): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                <?php endif; endforeach; ?>
                            </optgroup>
                            <optgroup label="Receitas">
                                <?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'receita'): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option>
                                <?php endif; endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="id_conta" class="form-label">Conta</label>
                        <select class="form-select" name="id_conta" id="id_conta" required>
                            <option value="">Selecione uma conta</option>
                            <?php foreach($lista_contas as $conta): ?>
                                <option value="<?php echo (int)$conta['id']; ?>" <?php echo ($conta_id === (int)$conta['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conta['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-custom-red">Salvar Lançamento</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LÓGICA PARA OCULTAR/MOSTRAR SALDO ---
        const btnToggleSaldo = document.getElementById('btn-toggle-saldo');
        const iconeSaldo = btnToggleSaldo ? btnToggleSaldo.querySelector('i') : null;
        const body = document.body;
        const LS_KEY = 'saldoVisivel';
        
        function atualizarVisibilidade() {
            if (!btnToggleSaldo || !iconeSaldo) return;
            const isVisible = localStorage.getItem(LS_KEY) !== 'false';
            if (isVisible) {
                body.classList.remove('saldo-oculto');
                iconeSaldo.classList.remove('bi-eye-slash-fill');
                iconeSaldo.classList.add('bi-eye-fill');
            } else {
                body.classList.add('saldo-oculto');
                iconeSaldo.classList.remove('bi-eye-fill');
                iconeSaldo.classList.add('bi-eye-slash-fill');
            }
        }
        
        if (btnToggleSaldo) {
            btnToggleSaldo.addEventListener('click', () => {
                const isCurrentlyVisible = localStorage.getItem(LS_KEY) !== 'false';
                localStorage.setItem(LS_KEY, !isCurrentlyVisible);
                atualizarVisibilidade();
            });
            atualizarVisibilidade();
        }
        
        // --- LÓGICA DO FILTRO DE DATA ---
        const selectMes = document.getElementById('selectMes');
        const selectAno = document.getElementById('selectAno');
        function atualizarDashboard() { const mes = selectMes.value; const ano = selectAno.value; const contaSel = (document.getElementById('selectConta')?.value || 'all'); window.location.href = `dashboard.php?mes=${mes}&ano=${ano}&conta=${encodeURIComponent(contaSel)}`; }
        if (selectMes && selectAno) {
            selectMes.addEventListener('change', atualizarDashboard);
            selectAno.addEventListener('change', atualizarDashboard);
            const selectConta = document.getElementById('selectConta');
            if (selectConta) { selectConta.addEventListener('change', atualizarDashboard); }
        }
        
        // --- LÓGICA DOS GRÁFICOS E FORMULÁRIOS AJAX ---
        const barChartCanvas = document.getElementById('barChart');
        if(barChartCanvas && <?php echo json_encode(!empty($barChartData)); ?>){ new Chart(barChartCanvas.getContext('2d'), { type: 'bar', data: { labels: <?php echo json_encode($barChartLabels); ?>, datasets: [{ label: 'Total Gasto (R$)', data: <?php echo json_encode($barChartData); ?>, backgroundColor: 'rgba(229, 9, 20, 0.6)', borderColor: 'rgba(229, 9, 20, 1)', borderWidth: 1, borderRadius: 5 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255, 255, 255, 0.1)' }, ticks: { color: '#adb5bd' } }, x: { grid: { display: false }, ticks: { color: '#adb5bd' } } } } }); }
        
        const pieChartCanvas = document.getElementById('pieChart');
        if(pieChartCanvas && <?php echo json_encode(!empty($pieChartData)); ?>){ new Chart(pieChartCanvas.getContext('2d'), { type: 'doughnut', data: { labels: <?php echo json_encode($pieChartLabels); ?>, datasets: [{ data: <?php echo json_encode($pieChartData); ?>, backgroundColor: <?php echo json_encode($pieChartColors); ?>, borderColor: '#1f1f1f', borderWidth: 3 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#f5f5f1' } }, tooltip: { callbacks: { label: function(c) { let l = c.label || ''; if(l) l += ': '; let v = c.parsed || 0; l += 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 }); return l; } } } } } }); }
        
        // --- CORRIGIR MODAL: Remover overlays ao abrir ---
        const modalNovoLancamento = document.getElementById('modalNovoLancamento');
        if (modalNovoLancamento) {
            modalNovoLancamento.addEventListener('show.bs.modal', function() {
                // Remover qualquer overlay que possa estar bloqueando
                document.querySelectorAll('.tourlite-overlay, .modal-overlay').forEach(el => {
                    if (el.id !== 'modalNovoLancamento') {
                        el.style.display = 'none';
                        el.style.visibility = 'hidden';
                        el.style.pointerEvents = 'none';
                    }
                });
            });
            
            modalNovoLancamento.addEventListener('shown.bs.modal', function() {
                // Garantir que o modal está funcional
                const firstInput = modalNovoLancamento.querySelector('input:not([type="hidden"])');
                if (firstInput) firstInput.focus();
            });
        }
        
        const formNovoLancamento = document.getElementById('formNovoLancamento');
        if(formNovoLancamento){ formNovoLancamento.addEventListener('submit', function(e){ e.preventDefault(); const d = new FormData(formNovoLancamento); const b = formNovoLancamento.querySelector('button[type="submit"]'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...'; fetch('salvar_transacao.php', { method: 'POST', body: d }).then(r => r.ok ? r.json() : r.json().then(e => { throw new Error(e.message || 'Ocorreu um erro.') })).then(d => { if(d.success){ showToast('Sucesso!', d.message); setTimeout(() => { const contaSel = (document.getElementById('selectConta')?.value || 'all'); window.location.href = `dashboard.php?mes=${selectMes.value}&ano=${selectAno.value}&conta=${encodeURIComponent(contaSel)}`; }, 1000); } else { showToast('Erro!', d.message, true); b.disabled = false; b.innerHTML = 'Salvar Lançamento'; } }).catch(e => { console.error('Erro:', e); showToast('Erro!', e.message, true); b.disabled = false; b.innerHTML = 'Salvar Lançamento'; }); }); }

        // --- LANÇAMENTO RÁPIDO COM IA ---
        const formIaRapida = document.getElementById('formIaRapida');
        if(formIaRapida) {
            formIaRapida.addEventListener('submit', function(e) {
                e.preventDefault();
                const inputIa = document.getElementById('inputIa');
                const btnIaSubmit = document.getElementById('btnIaSubmit');
                const texto = inputIa.value.trim();
                const contaSel = (document.getElementById('selectConta')?.value || 'all');
                const idConta = (contaSel !== 'all') ? parseInt(contaSel) : null;
                
                if (!texto) {
                    showToast('Atenção!', 'Por favor, digite uma descrição para o lançamento.', true);
                    return;
                }
                
                // Desabilitar botão e mostrar loading
                btnIaSubmit.disabled = true;
                const originalText = btnIaSubmit.innerHTML;
                btnIaSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processando...';
                
                fetch('processar_ia.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ texto: texto, id_conta: idConta })
                })
                .then(response => {
                    const isRateLimit = response.status === 429;
                    return response.json().then(data => ({
                        ...data,
                        status: response.status,
                        isRateLimit: isRateLimit
                    }));
                })
                .then(data => {
                    if(data.success) {
                        showToast('Sucesso!', data.message || 'Lançamento adicionado pela IA com sucesso!');
                        inputIa.value = '';
                        setTimeout(() => {
                            window.location.href = `dashboard.php?mes=${selectMes.value}&ano=${selectAno.value}&conta=${encodeURIComponent(contaSel)}`;
                        }, 1500);
                    } else {
                        // Tratar erro 429 (Rate Limit ou Cota Excedida)
                        if(data.isRateLimit || data.status === 429) {
                            const retryAfter = data.retry_after || 60;
                            const rateLimitInfo = data.rate_limit_info || {};
                            let message = data.message || 'Limite de requisições excedido.';
                            const isQuotaExceeded = data.quota_exceeded === true;
                            const isInternalRateLimit = data.internal_rate_limit === true;
                            
                            // Se for rate limit interno, mensagem diferente
                            if(isInternalRateLimit) {
                                showToast('Limite Interno do Sistema', message, true);
                                btnIaSubmit.disabled = true;
                                let countdown = Math.ceil(retryAfter);
                                const countdownInterval = setInterval(() => {
                                    countdown--;
                                    if(countdown > 0) {
                                        btnIaSubmit.innerHTML = `Aguarde ${countdown}s (interno)`;
                                    } else {
                                        clearInterval(countdownInterval);
                                        btnIaSubmit.disabled = false;
                                        btnIaSubmit.innerHTML = originalText;
                                    }
                                }, 1000);
                                btnIaSubmit.innerHTML = `Aguarde ${countdown}s (interno)`;
                                
                                // Adicionar informação
                                if(data.note) {
                                    console.log('Nota:', data.note);
                                }
                            }
                            // Se for cota excedida, não mostrar contador (é um problema mais grave)
                            else if(isQuotaExceeded) {
                                showToast('Cota da API Excedida', message, true);
                                btnIaSubmit.disabled = true;
                                btnIaSubmit.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Cota Excedida';
                                btnIaSubmit.style.opacity = '0.6';
                                
                                // Adicionar mensagem informativa abaixo do botão
                                const formText = document.querySelector('#formIaRapida .form-text');
                                if(formText) {
                                    formText.innerHTML = '<span class="text-warning"><i class="bi bi-info-circle me-1"></i>A cota da API foi excedida. Use o formulário manual abaixo para adicionar transações.</span>';
                                }
                            } else {
                                // Rate limit temporário da API
                                // Adicionar informações sobre o limite
                                if(rateLimitInfo.requests_last_minute !== undefined) {
                                    message += ` (${rateLimitInfo.requests_last_minute}/${rateLimitInfo.limit_per_minute} por minuto)`;
                                }
                                
                                showToast('Limite Temporário da API', message, true);
                                
                                // Desabilitar botão e mostrar contador
                                btnIaSubmit.disabled = true;
                                let countdown = Math.ceil(retryAfter);
                                const countdownInterval = setInterval(() => {
                                    countdown--;
                                    if(countdown > 0) {
                                        btnIaSubmit.innerHTML = `Aguarde ${countdown}s`;
                                    } else {
                                        clearInterval(countdownInterval);
                                        btnIaSubmit.disabled = false;
                                        btnIaSubmit.innerHTML = originalText;
                                    }
                                }, 1000);
                                
                                // Atualizar mensagem inicial
                                btnIaSubmit.innerHTML = `Aguarde ${countdown}s`;
                            }
                        } else {
                            // Outros erros
                            showToast('Erro da IA', data.message || 'Ocorreu um erro ao processar sua solicitação.', true);
                            btnIaSubmit.disabled = false;
                            btnIaSubmit.innerHTML = originalText;
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro de rede:', error);
                    showToast('Erro de Rede', 'Não foi possível se conectar ao servidor. Verifique sua conexão.', true);
                    btnIaSubmit.disabled = false;
                    btnIaSubmit.innerHTML = originalText;
                });
            });
        }
        
        // --- FUNCIONALIDADES DAS TAREFAS ---
        // Marcar tarefa como concluída
        document.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskId = this.dataset.id;
                const isChecked = this.checked;
                const taskItem = this.closest('.task-item');
                
                fetch('atualizar_status_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: taskId, status: isChecked ? 'concluida' : 'pendente' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (isChecked) {
                            taskItem.style.opacity = '0.6';
                            taskItem.querySelector('.task-title').style.textDecoration = 'line-through';
                            showToast('Tarefa Concluída!', 'Parabéns! Tarefa marcada como concluída.');
                            
                            // Atualizar contadores em tempo real
                            updateDashboardCounters();
                            
                            // Remover após 2 segundos
                            setTimeout(() => {
                                taskItem.style.transform = 'translateX(-100%)';
                                taskItem.style.transition = 'all 0.3s ease';
                                setTimeout(() => {
                                    taskItem.remove();
                                }, 300);
                            }, 2000);
                        } else {
                            taskItem.style.opacity = '1';
                            taskItem.querySelector('.task-title').style.textDecoration = 'none';
                            // Atualizar contadores
                            updateDashboardCounters();
                        }
                    } else {
                        showToast('Erro!', data.message, true);
                        this.checked = !isChecked; // Reverter
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro de Rede!', 'Não foi possível atualizar a tarefa.', true);
                    this.checked = !isChecked; // Reverter
                });
            });
        });
        
        // Timer Pomodoro simples
        let pomodoroActive = false;
        let currentTaskId = null;
        
        document.querySelectorAll('.btn-start-timer').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.dataset.id;
                
                if (pomodoroActive && currentTaskId === taskId) {
                    // Parar timer
                    pomodoroActive = false;
                    currentTaskId = null;
                    this.innerHTML = '<i class="bi bi-play-fill"></i>';
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-light');
                    showToast('Timer Pausado', 'Timer da tarefa foi pausado.');
                } else {
                    // Iniciar timer
                    pomodoroActive = true;
                    currentTaskId = taskId;
                    this.innerHTML = '<i class="bi bi-pause-fill"></i>';
                    this.classList.remove('btn-outline-light');
                    this.classList.add('btn-success');
                    showToast('Timer Iniciado!', 'Timer Pomodoro de 25 minutos iniciado para esta tarefa.');
                    
                    // Simular timer (25 minutos = 1500000ms, mas vamos usar 5 segundos para demo)
                    setTimeout(() => {
                        if (pomodoroActive && currentTaskId === taskId) {
                            pomodoroActive = false;
                            currentTaskId = null;
                            this.innerHTML = '<i class="bi bi-play-fill"></i>';
                            this.classList.remove('btn-success');
                            this.classList.add('btn-outline-light');
                            showToast('Timer Concluído!', 'Sessão Pomodoro finalizada! Hora de uma pausa.');
                            
                            // Vibração se disponível
                            if (navigator.vibrate) {
                                navigator.vibrate([200, 100, 200]);
                            }
                        }
                    }, 5000); // 5 segundos para demo (use 1500000 para 25 minutos reais)
                }
            });
        });
        
        // Função para atualizar contadores do dashboard
        function updateDashboardCounters() {
            fetch('get_task_stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualizar contadores de produtividade
                    const hojeElement = document.querySelector('.stat-number.text-success');
                    if (hojeElement) {
                        hojeElement.textContent = data.hoje.concluidas;
                    }
                    
                    const semanaElement = document.querySelector('.stat-number.text-info');
                    if (semanaElement) {
                        semanaElement.textContent = data.semana.concluidas;
                    }
                    
                    const pendentesElement = document.querySelector('.stat-number.text-warning');
                    if (pendentesElement) {
                        pendentesElement.textContent = data.pendentes;
                    }
                    
                    // Atualizar barra de progresso
                    const progressBar = document.querySelector('.progress-bar');
                    if (progressBar && data.hoje.total > 0) {
                        const progressPercent = (data.hoje.concluidas / data.hoje.total) * 100;
                        progressBar.style.width = progressPercent + '%';
                        
                        // Atualizar texto do progresso
                        const progressText = document.querySelector('small.text-muted');
                        if (progressText && progressText.textContent.includes('Progresso de hoje')) {
                            progressText.textContent = `Progresso de hoje: ${Math.round(progressPercent)}%`;
                        }
                    }
                }
            })
            .catch(error => console.error('Erro ao atualizar contadores do dashboard:', error));
        }
    });
</script>

<style>
/* ================================================== */
/* DASHBOARD MODERNO - DESIGN SYSTEM */
/* ================================================== */

:root {
    --primary: #e50914;
    --primary-light: #ff3d47;
    --primary-dark: #b3070f;
    --success: #00d68f;
    --danger: #ff6b6b;
    --warning: #ffc107;
    --info: #4da6ff;
    --dark: #0d0d0f;
    --card-bg: rgba(20, 20, 25, 0.8);
    --text-primary: #ffffff;
    --text-secondary: rgba(255, 255, 255, 0.6);
    --text-muted: rgba(255, 255, 255, 0.4);
    --border: rgba(255, 255, 255, 0.08);
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
}

/* ================================================== */
/* HEADER DO DASHBOARD */
/* ================================================== */

.dashboard-header {
    margin-bottom: 2rem;
}

.dashboard-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.dashboard-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.dashboard-subtitle {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0.25rem 0 0 0;
}

.dashboard-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.btn-toggle-saldo {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    background: var(--card-bg);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-toggle-saldo:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.btn-novo-lancamento {
    height: 44px;
    padding: 0 1.25rem;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
}

.btn-novo-lancamento:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
}

.btn-novo-lancamento i {
    font-size: 1.1rem;
    line-height: 1;
}

/* FILTROS */
.dashboard-filtros {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.filtro-group {
    position: relative;
}

.filtro-select {
    height: 42px;
    padding: 0 2.5rem 0 1rem;
    border-radius: var(--radius-md);
    background: var(--card-bg);
    border: 1px solid var(--border);
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    transition: all 0.3s ease;
    min-width: 140px;
}

.filtro-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.2);
}

.filtro-conta .filtro-select {
    min-width: 180px;
}

/* ================================================== */
/* CARDS KPI */
/* ================================================== */

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.kpi-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.kpi-icon {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.kpi-receitas .kpi-icon {
    background: rgba(0, 214, 143, 0.15);
    color: var(--success);
}

.kpi-despesas .kpi-icon {
    background: rgba(255, 107, 107, 0.15);
    color: var(--danger);
}

.kpi-saldo .kpi-icon {
    background: rgba(77, 166, 255, 0.15);
    color: var(--info);
}

.kpi-saldo.positivo .kpi-icon {
    background: rgba(0, 214, 143, 0.15);
    color: var(--success);
}

.kpi-saldo.negativo .kpi-icon {
    background: rgba(255, 107, 107, 0.15);
    color: var(--danger);
}

.kpi-content {
    flex: 1;
    min-width: 0;
}

.kpi-label {
    display: block;
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.kpi-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    font-family: 'Roboto Mono', monospace;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.kpi-receitas .kpi-value { color: var(--success); }
.kpi-despesas .kpi-value { color: var(--danger); }
.kpi-saldo.positivo .kpi-value { color: var(--success); }
.kpi-saldo.negativo .kpi-value { color: var(--danger); }

.kpi-indicator {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.kpi-receitas .kpi-indicator { background: linear-gradient(90deg, var(--success), transparent); }
.kpi-despesas .kpi-indicator { background: linear-gradient(90deg, var(--danger), transparent); }
.kpi-saldo .kpi-indicator { background: linear-gradient(90deg, var(--info), transparent); }

/* ================================================== */
/* CARD DE IA */
/* ================================================== */

.ia-card {
    background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(20, 20, 25, 0.9) 100%);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(229, 9, 20, 0.2);
}

.ia-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.ia-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.ia-title h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.ia-title p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0.25rem 0 0 0;
}

.ia-input-wrapper {
    display: flex;
    gap: 0.75rem;
}

.ia-input {
    flex: 1;
    height: 50px;
    padding: 0 1.25rem;
    border-radius: var(--radius-md);
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border);
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.ia-input::placeholder {
    color: var(--text-muted);
}

.ia-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.2);
}

.ia-submit {
    height: 50px;
    padding: 0 1.5rem;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.ia-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
}

/* ================================================== */
/* GRID PRINCIPAL */
/* ================================================== */

.dashboard-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
}

.dashboard-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ================================================== */
/* CARDS MODERNOS */
/* ================================================== */

.dashboard-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    overflow: hidden;
}

.card-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
}

.card-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.tarefas-icon { background: rgba(77, 166, 255, 0.15); color: var(--info); }
.barras-icon { background: rgba(255, 107, 107, 0.15); color: var(--danger); }
.lancamentos-icon { background: rgba(0, 214, 143, 0.15); color: var(--success); }

.card-title-modern {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.card-subtitle-modern {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0.15rem 0 0 0;
}

.btn-ver-mais {
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.btn-ver-mais:hover {
    color: var(--primary);
}

.card-body-modern {
    padding: 1rem 1.5rem 1.5rem;
}

.card-header-compact {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

.card-header-compact i {
    color: var(--primary);
}

/* ================================================== */
/* TAREFAS */
/* ================================================== */

.tarefas-lista {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.tarefa-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: var(--radius-md);
    border-left: 3px solid transparent;
    transition: all 0.3s ease;
}

.tarefa-item[data-prioridade="alta"] { border-left-color: var(--danger); }
.tarefa-item[data-prioridade="média"] { border-left-color: var(--warning); }
.tarefa-item[data-prioridade="baixa"] { border-left-color: var(--success); }

.tarefa-item:hover {
    background: rgba(255, 255, 255, 0.06);
    transform: translateX(4px);
}

.tarefa-check input {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
    cursor: pointer;
}

.tarefa-content {
    flex: 1;
    min-width: 0;
}

.tarefa-titulo {
    display: block;
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.tarefa-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.tarefa-prioridade {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    text-transform: uppercase;
}

.tarefa-prioridade.alta { background: rgba(255, 107, 107, 0.2); color: var(--danger); }
.tarefa-prioridade.média { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
.tarefa-prioridade.baixa { background: rgba(0, 214, 143, 0.2); color: var(--success); }

.tarefa-data, .tarefa-tempo {
    font-size: 0.8rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.btn-timer {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-timer:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* ================================================== */
/* PRODUTIVIDADE */
/* ================================================== */

.produtividade-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    text-align: center;
}

.prod-stat {
    display: flex;
    flex-direction: column;
}

.prod-value {
    font-size: 1.5rem;
    font-weight: 700;
    font-family: 'Roboto Mono', monospace;
}

.prod-value.verde { color: var(--success); }
.prod-value.azul { color: var(--info); }
.prod-value.amarelo { color: var(--warning); }

.prod-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.progresso-wrapper {
    padding: 0 1.25rem 1.25rem;
}

.progresso-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progresso-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), var(--primary));
    border-radius: 3px;
    transition: width 0.5s ease;
}

.progresso-text {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* ================================================== */
/* GRÁFICOS */
/* ================================================== */

.grafico-wrapper {
    padding: 1rem 1.25rem 1.25rem;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chart-container-modern {
    height: 300px;
    width: 100%;
}

/* ================================================== */
/* LANÇAMENTOS */
/* ================================================== */

.lancamentos-lista {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.lancamento-item {
    display: flex;
    align-items: stretch;
    background: rgba(255, 255, 255, 0.03);
    border-radius: var(--radius-sm);
    overflow: hidden;
    transition: all 0.3s ease;
}

.lancamento-item:hover {
    background: rgba(255, 255, 255, 0.06);
    transform: translateX(4px);
}

.lancamento-indicador {
    width: 3px;
    flex-shrink: 0;
}

.lancamento-item.receita .lancamento-indicador { background: var(--success); }
.lancamento-item.despesa .lancamento-indicador { background: var(--danger); }

.lancamento-content {
    flex: 1;
    padding: 0.875rem 1rem;
    min-width: 0;
}

.lancamento-principal {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0.35rem;
}

.lancamento-descricao {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.lancamento-valor {
    font-family: 'Roboto Mono', monospace;
    font-size: 0.95rem;
    font-weight: 600;
    white-space: nowrap;
}

.lancamento-valor.receita { color: var(--success); }
.lancamento-valor.despesa { color: var(--danger); }

.lancamento-detalhes {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.lancamento-categoria {
    font-size: 0.75rem;
    color: var(--text-muted);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

.lancamento-data {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* ================================================== */
/* ESTADOS VAZIOS */
/* ================================================== */

.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    display: block;
    opacity: 0.5;
}

.empty-state p {
    margin-bottom: 1rem;
}

.empty-state.small {
    padding: 1rem;
}

.empty-state.small i {
    font-size: 1.5rem;
}

.btn-add-tarefa {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-sm);
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-secondary);
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-add-tarefa:hover {
    background: var(--primary);
    color: white;
}

/* ================================================== */
/* OCULTAR SALDO */
/* ================================================== */

body.saldo-oculto .valor-sensivel {
    filter: blur(8px);
    user-select: none;
}

/* ================================================== */
/* MODAL */
/* ================================================== */

/* ================================================== */
/* MODAL - CORREÇÃO DEFINITIVA */
/* ================================================== */

/* Estilo visual do modal */
#modalNovoLancamento .modal-content {
    background: #1a1a1e !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5) !important;
    overflow: visible !important;
}

/* Remove pseudo-elementos que podem criar overlays */
#modalNovoLancamento .modal-content::before,
#modalNovoLancamento .modal-content::after,
#modalNovoLancamento .modal-content > *::before,
#modalNovoLancamento .modal-content > *::after {
    display: none !important;
    content: none !important;
}

#modalNovoLancamento .modal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.25rem 1.5rem;
    background: transparent !important;
}

#modalNovoLancamento .modal-body {
    padding: 1.5rem;
    background: transparent !important;
}

#modalNovoLancamento .modal-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1rem 1.5rem;
    background: transparent !important;
}

#modalNovoLancamento .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
    opacity: 0.7;
}

#modalNovoLancamento .btn-close:hover {
    opacity: 1;
}

#modalNovoLancamento .form-label {
    color: #fff;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

#modalNovoLancamento .form-control,
#modalNovoLancamento .form-select {
    background: rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: #fff !important;
    padding: 0.75rem 1rem;
    border-radius: 8px;
}

#modalNovoLancamento .form-control:focus,
#modalNovoLancamento .form-select:focus {
    background: rgba(0, 0, 0, 0.5) !important;
    border-color: #e50914 !important;
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.25) !important;
    color: #fff !important;
}

#modalNovoLancamento .form-control::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.form-control, .form-select {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--border);
    color: var(--text-primary);
    border-radius: var(--radius-sm);
    padding: 0.75rem 1rem;
}

.form-control:focus, .form-select:focus {
    background: rgba(0, 0, 0, 0.4);
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.2);
    color: var(--text-primary);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.btn-custom-red {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-sm);
}

.btn-custom-red:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
}

/* ================================================== */
/* NOTIFICAÇÕES */
/* ================================================== */

.onesignal-customlink-container {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    max-width: 450px;
    margin: 2rem auto;
    text-align: center;
}

.notify-button {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: white;
    padding: 1rem 2rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.3s ease;
}

.notify-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
}

.notify-text {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-top: 1.25rem;
    line-height: 1.6;
}

/* ================================================== */
/* RESPONSIVIDADE */
/* ================================================== */

@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: 1fr 320px;
    }
}

@media (max-width: 991px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-sidebar {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .dashboard-sidebar > * {
        flex: 1;
        min-width: 280px;
    }
}

@media (max-width: 767px) {
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .kpi-card {
        padding: 1.25rem;
    }
    
    .kpi-value {
        font-size: 1.25rem;
    }
    
    .dashboard-header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .dashboard-title-section {
        text-align: center;
    }
    
    .dashboard-actions {
        justify-content: center;
    }
    
    .dashboard-filtros {
        justify-content: center;
    }
    
    .filtro-select {
        min-width: 0;
        flex: 1;
    }
    
    .ia-input-wrapper {
        flex-direction: column;
    }
    
    .ia-submit {
        width: 100%;
        justify-content: center;
    }
    
    .dashboard-sidebar {
        flex-direction: column;
    }
    
    .dashboard-sidebar > * {
        min-width: 100%;
    }
    
    .tarefa-item {
        flex-wrap: wrap;
    }
    
    .btn-timer {
        margin-left: auto;
    }
    
    .card-header-modern {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .lancamento-principal {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .lancamento-valor {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .dashboard-title {
        font-size: 1.5rem;
    }
    
    .btn-novo-lancamento span {
        display: none;
    }
    
    .btn-novo-lancamento {
        padding: 0;
        width: 44px;
        justify-content: center;
    }
    
    .produtividade-stats {
        padding: 0.75rem;
    }
    
    .prod-value {
        font-size: 1.25rem;
    }
}
</style>

<?php
require_once 'templates/footer.php';
?>