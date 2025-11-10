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



<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Dashboard</h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento"><i class="bi bi-plus-lg me-2"></i>Novo Lançamento</button>
</div>

<main class="container-fluid p-0">
    <div class="row g-4">

        <div class="col-12">
            <div class="card card-glass">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h4 class="card-title mb-0">Resumo de <?php echo $nome_mes_ano; ?></h4>
                    <div class="d-flex align-items-center gap-3">
                        <i id="btn-toggle-saldo" class="bi bi-eye-fill fs-5 text-muted" title="Mostrar/Ocultar valores"></i>
                        <form id="filtroMesAno" class="d-flex gap-2">
                            <select name="mes" id="selectMes" class="form-select form-select-sm" style="width: 130px;"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>" <?php echo ($m == $mes_selecionado) ? 'selected' : ''; ?>><?php echo ucfirst(strftime('%B', mktime(0, 0, 0, $m, 1))); ?></option><?php endfor; ?></select>
                            <select name="ano" id="selectAno" class="form-select form-select-sm" style="width: 90px;"><?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): ?><option value="<?php echo $a; ?>" <?php echo ($a == $ano_selecionado) ? 'selected' : ''; ?>><?php echo $a; ?></option><?php endfor; ?></select>
                            <select name="conta" id="selectConta" class="form-select form-select-sm" style="width: 180px;">
                                <option value="all" <?php echo ($conta_id === 0) ? 'selected' : ''; ?>>Todas as contas</option>
                                <?php foreach($lista_contas as $conta): ?>
                                    <option value="<?php echo (int)$conta['id']; ?>" <?php echo ($conta_id === (int)$conta['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($conta['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row text-center gy-3">
                        <div class="col-sm-4"><h6 class="text-white-50"><i class="bi bi-graph-up-arrow"></i> Receitas</h6><p class="display-6 fw-bold valor-sensivel">R$ <?php echo number_format($totalReceitas, 2, ',', '.'); ?></p></div>
                        <div class="col-sm-4"><h6 class="text-white-50"><i class="bi bi-graph-down-arrow"></i> Despesas</h6><p class="display-6 fw-bold valor-sensivel">R$ <?php echo number_format($totalDespesas, 2, ',', '.'); ?></p></div>
                        <div class="col-sm-4"><h6 class="text-white-50"><i class="bi bi-wallet2"></i> Saldo</h6><p class="display-6 fw-bold valor-sensivel">R$ <?php echo number_format($saldoMes, 2, ',', '.'); ?></p></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12"><div class="card card-glass"><div class="card-body p-4"><form id="formIaRapida"><label for="inputIa" class="form-label h5">Lançamento Rápido com IA</label><div class="input-group"><span class="input-group-text"><i class="bi bi-magic"></i></span><input type="text" id="inputIa" class="form-control" placeholder="Ex: Comprei pizza por R$ 25 hoje" required><button class="btn btn-danger" type="submit" id="btnIaSubmit">Lançar</button></div><div class="form-text">Digite naturalmente: o que foi, quanto custou e quando. A IA vai entender e categorizar automaticamente!</div></form></div></div></div>
        
        <!-- Seção de Tarefas no Dashboard -->
        <div class="col-lg-8">
            <div class="card card-glass h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-list-task me-2"></i>Tarefas de Hoje
                        </h4>
                        <a href="tarefas.php" class="btn btn-outline-light btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Ver Todas
                        </a>
                    </div>
                    
                    <?php if(empty($tarefas_hoje)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle" style="font-size: 3rem; color: #28a745; opacity: 0.7;"></i>
                            <p class="text-muted mt-2">Nenhuma tarefa para hoje!</p>
                            <a href="tarefas.php" class="btn btn-outline-secondary btn-sm">Adicionar Tarefa</a>
                        </div>
                    <?php else: ?>
                        <div class="task-list">
                            <?php foreach($tarefas_hoje as $tarefa): ?>
                                <div class="task-item d-flex align-items-center p-3 mb-2 rounded" style="background: rgba(255,255,255,0.05); border-left: 4px solid <?php echo $tarefa['prioridade'] == 'Alta' ? '#dc3545' : ($tarefa['prioridade'] == 'Média' ? '#ffc107' : '#28a745'); ?>;">
                                    <div class="form-check me-3">
                                        <input class="form-check-input task-checkbox" type="checkbox" data-id="<?php echo $tarefa['id']; ?>" style="transform: scale(1.2);">
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="task-title fw-semibold"><?php echo htmlspecialchars($tarefa['descricao']); ?></div>
                                        <div class="task-meta d-flex align-items-center gap-3 mt-1">
                                            <span class="badge <?php echo getPrioridadeBadge($tarefa['prioridade']); ?> badge-sm">
                                                <?php echo $tarefa['prioridade']; ?>
                                            </span>
                                            <?php if($tarefa['data_limite']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?php echo date('d/m', strtotime($tarefa['data_limite'])); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if($tarefa['tempo_estimado'] > 0): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php 
                                                        $h = floor($tarefa['tempo_estimado'] / 60);
                                                        $m = $tarefa['tempo_estimado'] % 60;
                                                        echo ($h > 0 ? $h.'h ' : '') . ($m > 0 ? $m.'min' : '');
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="task-actions">
                                        <button class="btn btn-outline-light btn-sm btn-start-timer" data-id="<?php echo $tarefa['id']; ?>" title="Iniciar Timer">
                                            <i class="bi bi-play-fill"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="tarefas.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-right me-1"></i>Ver Todas as Tarefas
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Stats de Tarefas -->
            <div class="card card-glass mb-3">
                <div class="card-body p-3">
                    <h6 class="card-title mb-3">
                        <i class="bi bi-graph-up me-2"></i>Produtividade
                    </h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="stat-number h5 mb-1 text-success">
                                <?php echo $stats_tarefas['hoje']['concluidas']; ?>
                                <?php if($stats_tarefas['hoje']['total'] > 0): ?>
                                    <small class="d-block" style="font-size: 0.7rem;">/<?php echo $stats_tarefas['hoje']['total']; ?></small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Hoje</small>
                        </div>
                        <div class="col-4">
                            <div class="stat-number h5 mb-1 text-info">
                                <?php echo $stats_tarefas['semana']['concluidas']; ?>
                                <?php if($stats_tarefas['semana']['total'] > 0): ?>
                                    <small class="d-block" style="font-size: 0.7rem;">/<?php echo $stats_tarefas['semana']['total']; ?></small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Semana</small>
                        </div>
                        <div class="col-4">
                            <div class="stat-number h5 mb-1 text-warning"><?php echo $stats_tarefas['pendentes']; ?></div>
                            <small class="text-muted">Pendentes</small>
                        </div>
                    </div>
                    
                    <!-- Barra de Progresso -->
                    <?php 
                        $progresso_hoje = $stats_tarefas['hoje']['total'] > 0 ? 
                            ($stats_tarefas['hoje']['concluidas'] / $stats_tarefas['hoje']['total']) * 100 : 0;
                    ?>
                    <div class="progress mt-3" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $progresso_hoje; ?>%"></div>
                    </div>
                    <small class="text-muted">Progresso de hoje: <?php echo round($progresso_hoje); ?>%</small>
                </div>
            </div>
            
            <!-- Gráfico de Despesas por Categoria -->
            <div class="card card-glass">
                <div class="card-body p-4 d-flex flex-column">
                    <h4 class="card-title mb-3">Despesas por Categoria</h4>
                    <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                        <?php if(empty($pieChartData)): ?>
                            <p class="text-muted">Nenhuma despesa em <?php echo ucfirst(strftime('%B', $data_base->getTimestamp())); ?>.</p>
                        <?php else: ?>
                            <div class="chart-container"><canvas id="pieChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12"><div class="card card-glass"><div class="card-body p-4"><h4 class="card-title mb-3">Despesas Diárias em <?php echo ucfirst(strftime('%B', $data_base->getTimestamp())); ?></h4><div class="chart-container"><canvas id="barChart"></canvas></div></div></div></div>
        
        <div class="col-12"><div class="card card-glass"><div class="card-body p-4"><h4 class="card-title mb-3">Últimos Lançamentos (Geral)</h4><ul class="list-group list-group-flush"><?php if(empty($ultimos_lancamentos)): ?><li class="list-group-item text-center text-muted" style="background: transparent; border-color: var(--border-color);">Nenhum lançamento ainda.</li><?php else: foreach ($ultimos_lancamentos as $lancamento): ?><li class="list-group-item d-flex justify-content-between align-items-center" style="background: transparent; border-color: var(--border-color);"><div><span class="fw-bold"><?php echo htmlspecialchars($lancamento['descricao']); ?></span><small class="d-block text-muted"><?php echo htmlspecialchars($lancamento['nome_categoria'] ?? 'Sem Categoria'); ?> - <?php echo date('d/m/Y', strtotime($lancamento['data_transacao'])); ?></small></div><span class="fw-bold valor-sensivel" style="font-family: 'Roboto Mono', monospace;"><?php echo ($lancamento['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($lancamento['valor'], 2, ',', '.'); ?></span></li><?php endforeach; endif; ?></ul><div class="d-grid mt-3"><a href="extrato_completo.php" class="btn btn-outline-secondary">Ver Extrato Completo</a></div></div></div></div>
    </div>
</main>

<div class="modal fade" id="modalNovoLancamento" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="modalLabel"><i class="bi bi-pencil-square me-2"></i>Novo Lançamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form action="salvar_transacao.php" method="POST" id="formNovoLancamento"><div class="modal-body"><div class="mb-3"><label for="descricao" class="form-label">Descrição</label><input type="text" class="form-control" id="descricao" name="descricao" placeholder="Ex: Almoço, Salário" required></div><div class="row"><div class="col-md-6 mb-3"><label for="valor" class="form-label">Valor (R$)</label><input type="number" class="form-control" id="valor" name="valor" step="0.01" min="0" placeholder="25,50" required></div><div class="col-md-6 mb-3"><label for="data_transacao" class="form-label">Data</label><input type="date" class="form-control" id="data_transacao" name="data_transacao" value="<?php echo date('Y-m-d'); ?>" required></div></div><div class="mb-3"><label for="id_categoria" class="form-label">Categoria</label><select class="form-select" name="id_categoria" id="id_categoria" required><option value="">Selecione uma categoria</option><optgroup label="Despesas"><?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'despesa'): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option><?php endif; endforeach; ?></optgroup><optgroup label="Receitas"><?php foreach($lista_categorias as $cat): if($cat['tipo'] == 'receita'): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nome']); ?></option><?php endif; endforeach; ?></optgroup></select></div><div class="mb-3"><label for="id_conta" class="form-label">Conta</label><select class="form-select" name="id_conta" id="id_conta" required><option value="">Selecione uma conta</option><option value="<?php echo ($conta_id > 0) ? $conta_id : ''; ?>" <?php echo ($conta_id > 0) ? 'selected' : ''; ?>><?php echo ($conta_id > 0) ? (htmlspecialchars(array_values(array_filter($lista_contas, fn($c) => (int)$c['id'] === $conta_id))[0]['nome'] ?? 'Selecionada')) : '—'; ?></option><?php foreach($lista_contas as $conta): ?><option value="<?php echo (int)$conta['id']; ?>"><?php echo htmlspecialchars($conta['nome']); ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-custom-red">Salvar Lançamento</button></div></form></div></div></div>

<div class="onesignal-customlink-container">
  <button class="notify-button">
    🔔 Ativar Notificações
  </button>
  <p class="notify-text">
    Todas as notificações importantes chegam aqui. Ative para não perder nada!
  </p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LÓGICA PARA OCULTAR/MOSTRAR SALDO ---
        const btnToggleSaldo = document.getElementById('btn-toggle-saldo');
        const body = document.body;
        const LS_KEY = 'saldoVisivel';
        function atualizarVisibilidade() { const isVisible = localStorage.getItem(LS_KEY) !== 'false'; if (isVisible) { body.classList.remove('saldo-oculto'); btnToggleSaldo.classList.remove('bi-eye-slash-fill'); btnToggleSaldo.classList.add('bi-eye-fill'); } else { body.classList.add('saldo-oculto'); btnToggleSaldo.classList.remove('bi-eye-fill'); btnToggleSaldo.classList.add('bi-eye-slash-fill'); } }
        if (btnToggleSaldo) { btnToggleSaldo.addEventListener('click', () => { const isCurrentlyVisible = localStorage.getItem(LS_KEY) !== 'false'; localStorage.setItem(LS_KEY, !isCurrentlyVisible); atualizarVisibilidade(); }); atualizarVisibilidade(); }
        
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
                            
                            // Se for cota excedida, não mostrar contador (é um problema mais grave)
                            if(isQuotaExceeded) {
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
                                // Adicionar informações sobre o limite
                                if(rateLimitInfo.requests_last_minute !== undefined) {
                                    message += ` (${rateLimitInfo.requests_last_minute}/${rateLimitInfo.limit_per_minute} por minuto)`;
                                }
                                
                                showToast('Limite de Requisições', message, true);
                                
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
:root {
    --primary-red: #e50914;
    --dark-bg: #111;
    --card-bg-light: rgba(30, 30, 30, 0.5); /* Fundo semi-transparente */
    --text-light: #f5f5f1;
    --border-color: rgba(255, 255, 255, 0.1);
    --placeholder-color: #777;
    --success-color: #00b894;
    --danger-color: #e50914;
    --info-color: #0984e3;
    --border-radius: 12px;
}

body {
    background-color: var(--dark-bg);
    color: var(--text-light);
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ---------------------------------- */
/* Estilos do Dashboard      */
/* ---------------------------------- */

.container-fluid {
    max-width: 1400px;
}

h1, h2, h3, h4, h5, h6 {
    color: var(--text-light);
}

.d-flex.justify-content-between.mb-4 h1 {
    font-weight: 600;
}

/* Botão Principal */
.btn-danger {
    background-color: var(--primary-red);
    border-color: var(--primary-red);
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    background-color: #c00711;
    border-color: #c00711;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(229, 9, 20, 0.3);
}

/* Estilo para os cards */
.card-glass {
    background: var(--card-bg-light);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card-glass:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.3);
}

.card-header {
    background: transparent;
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
}

.card-title {
    font-weight: 600;
}

/* Estilo para o Resumo do Mês */
.resumo-card .display-6 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-top: 0.5rem;
    position: relative;
    color: var(--text-light);
}

.resumo-card .col-sm-4:nth-child(1) p { color: var(--success-color); }
.resumo-card .col-sm-4:nth-child(2) p { color: var(--danger-color); }
.resumo-card .col-sm-4:nth-child(3) p {
    color: #ffd700; /* Dourado para o saldo */
}

/* ===== SEÇÃO DE TAREFAS NO DASHBOARD ===== */
.task-item {
    transition: all 0.3s ease;
    border-radius: 8px;
}

.task-item:hover {
    background: rgba(255,255,255,0.08) !important;
    transform: translateY(-1px);
}

.task-checkbox {
    accent-color: var(--primary-red);
}

.task-title {
    color: var(--text-light);
    font-size: 1rem;
}

.task-meta .badge {
    font-size: 0.7rem;
    padding: 0.25em 0.5em;
}

.btn-start-timer {
    transition: all 0.3s ease;
}

.btn-start-timer:hover {
    transform: scale(1.1);
}

.stat-number {
    font-weight: 700;
    font-family: 'Roboto Mono', monospace;
}

.progress {
    background-color: rgba(255,255,255,0.1);
}

.progress-bar {
    transition: width 0.5s ease;
}

/* Animações para tarefas concluídas */
.task-completed {
    opacity: 0.6;
    transform: scale(0.98);
}

.task-completed .task-title {
    text-decoration: line-through;
    color: var(--text-secondary);
}

/* Responsividade para mobile */
@media (max-width: 768px) {
    .task-item {
        padding: 1rem !important;
    }
    
    .task-meta {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }
    
    .task-actions {
        margin-top: 0.5rem;
    }
    
    .btn-start-timer {
        width: 100%;
        justify-content: center;
    }
}

/* Input de Lançamento Rápido com IA */
#formIaRapida .input-group-text {
    background: var(--primary-red);
    color: white;
    border: none;
    border-radius: var(--border-radius) 0 0 var(--border-radius);
}

#formIaRapida .form-control {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    color: var(--text-light);
}

#formIaRapida .form-control:focus {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.2);
}

#formIaRapida .form-control::placeholder {
    color: var(--placeholder-color);
}

#formIaRapida .btn {
    background-color: var(--primary-red);
    border-color: var(--primary-red);
    font-weight: 600;
}

.form-text {
    color: var(--placeholder-color) !important;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

/* Gráficos */
.chart-container {
    height: 350px;
    width: 100%;
}

.card.h-100 {
    height: 100% !important;
}

/* Lista de Últimos Lançamentos */
.list-group-item {
    background: transparent !important;
    border-color: var(--border-color) !important;
    padding: 1rem 1.25rem;
}

.list-group-item:hover {
    background-color: rgba(255, 255, 255, 0.05) !important;
}

.list-group-item small {
    color: #999 !important;
}

.list-group-item .valor-sensivel {
    font-weight: 700;
}

.list-group-item .valor-sensivel:first-of-type {
    color: var(--success-color);
}

.list-group-item .valor-sensivel:last-of-type {
    color: var(--danger-color);
}

/* Modal */
.modal-content {
    background: #1e1e1e;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
}

.modal-header {
    border-bottom-color: var(--border-color);
}

.modal-footer {
    border-top-color: var(--border-color);
}

.form-control, .form-select {
    background-color: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    color: var(--text-light);
}

.form-control:focus, .form-select:focus {
    background-color: rgba(255, 255, 255, 0.1);
    border-color: var(--primary-red);
    box-shadow: 0 0 0 0.25rem rgba(229, 9, 20, 0.25);
}

.form-control::placeholder {
    color: var(--placeholder-color);
}

.btn-custom-red {
    background-color: var(--primary-red);
    border-color: var(--primary-red);
    color: var(--text-light);
}

.btn-custom-red:hover {
    background-color: #c00711;
    border-color: #c00711;
}

/* Ocultar Saldo */
body.saldo-oculto .valor-sensivel::after {
    content: '•••••';
    background-color: #333;
    color: transparent;
    border-radius: 4px;
    display: inline-block;
    padding: 0 8px;
    letter-spacing: 2px;
}
body.saldo-oculto .valor-sensivel {
    font-size: 0; /* Oculta o texto real */
}

/* Botão de Notificações */
.onesignal-customlink-container {
    background: linear-gradient(145deg, #1a1a1a, #111111);
    border: none;
    padding: 2rem;
    border-radius: 16px;
    max-width: 450px;
    margin: 2rem auto;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.onesignal-customlink-container::before {
    content: '';
    position: absolute;
    top: -50px;
    left: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(229, 9, 20, 0.4), transparent 70%);
    border-radius: 50%;
    transition: all 0.5s ease-out;
    opacity: 0.8;
}

.onesignal-customlink-container:hover {
    transform: translateY(-5px);
}

.onesignal-customlink-container:hover::before {
    transform: scale(1.5);
}

.notify-button {
    background: linear-gradient(45deg, #e50914, #ff4d4d);
    color: white;
    border: none;
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.3s ease, transform 0.2s;
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
}

.notify-button:hover {
    background: linear-gradient(45deg, #ff4d4d, #e50914);
    transform: translateY(-3px);
}

.notify-text {
    color: #bbb;
    font-size: 1rem;
    margin-top: 1.5rem;
    line-height: 1.5;
}

/* Rodapé */
footer {
    padding: 2rem 0;
    text-align: center;
    color: #888;
    margin-top: auto; /* Empurra o rodapé para baixo */
}

/* Responsividade */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 1rem;
    }
    .resumo-card .display-6 {
        font-size: 2rem;
    }
    .chart-container {
        height: 300px;
    }
}

/* Responsividade do filtro de contas (evita overflow no mobile) */
@media (max-width: 576px) {
    #filtroMesAno {
        flex-wrap: wrap;
        width: 100%;
    }
    #selectConta {
        width: 100% !important;
        max-width: 100%;
    }
}
</style>

<?php
require_once 'templates/footer.php';
?>