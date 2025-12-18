<?php
// relatorios.php (Página de Relatórios Avançados)

require_once 'templates/header.php';

// --- LÓGICA DE FILTRO DE DATA ---
// Define o período padrão: o mês atual
$data_inicio_padrao = date('Y-m-01');
$data_fim_padrao = date('Y-m-t');

// Pega as datas da URL, se existirem, senão usa o padrão
$data_inicio = $_GET['inicio'] ?? $data_inicio_padrao;
$data_fim = $_GET['fim'] ?? $data_fim_padrao;

// Inicializa variáveis
$kpis = ['total_receitas' => 0, 'total_despesas' => 0, 'saldo' => 0, 'gasto_medio_diario' => 0];
$lineChartLabels = [];
$lineChartDataReceitas = [];
$lineChartDataDespesas = [];
$transacoes_filtradas = [];

try {
    // --- QUERY 1: KPIs (Indicadores Chave) do Período ---
    $sql_kpis = "SELECT 
                    SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as total_receitas,
                    SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as total_despesas
                 FROM transacoes 
                 WHERE id_usuario = ? AND data_transacao BETWEEN ? AND ?";
    $stmt_kpis = $pdo->prepare($sql_kpis);
    $stmt_kpis->execute([$userId, $data_inicio, $data_fim]);
    $resumo_periodo = $stmt_kpis->fetch();
    
    $kpis['total_receitas'] = $resumo_periodo['total_receitas'] ?? 0;
    $kpis['total_despesas'] = $resumo_periodo['total_despesas'] ?? 0;
    $kpis['saldo'] = $kpis['total_receitas'] - $kpis['total_despesas'];
    
    // Calcula o gasto médio diário
    $dias_no_periodo = (new DateTime($data_inicio))->diff(new DateTime($data_fim))->days + 1;
    $kpis['gasto_medio_diario'] = ($dias_no_periodo > 0) ? $kpis['total_despesas'] / $dias_no_periodo : 0;

    // --- QUERY 2: Dados para o Gráfico de Linhas Evolutivo ---
    $sql_line_chart = "SELECT 
                            DATE(data_transacao) as dia,
                            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as receitas_dia,
                            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as despesas_dia
                       FROM transacoes
                       WHERE id_usuario = ? AND data_transacao BETWEEN ? AND ?
                       GROUP BY DATE(data_transacao)
                       ORDER BY dia ASC";
    $stmt_line_chart = $pdo->prepare($sql_line_chart);
    $stmt_line_chart->execute([$userId, $data_inicio, $data_fim]);
    foreach($stmt_line_chart->fetchAll() as $dado_dia) {
        $lineChartLabels[] = date('d/m', strtotime($dado_dia['dia']));
        $lineChartDataReceitas[] = $dado_dia['receitas_dia'];
        $lineChartDataDespesas[] = $dado_dia['despesas_dia'];
    }

    // --- QUERY 3: Tabela de Transações do Período ---
    $stmt_transacoes = $pdo->prepare("SELECT t.*, c.nome as nome_categoria FROM transacoes t LEFT JOIN categorias c ON t.id_categoria = c.id WHERE t.id_usuario = ? AND t.data_transacao BETWEEN ? AND ? ORDER BY t.data_transacao DESC, t.id DESC");
    $stmt_transacoes->execute([$userId, $data_inicio, $data_fim]);
    $transacoes_filtradas = $stmt_transacoes->fetchAll();

} catch (PDOException $e) {
    die("Erro ao buscar dados para os relatórios: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-column flex-md-row">
    <h1 class="h2 mb-3 mb-md-0">Relatórios</h1>
</div>

<div class="card card-custom mb-4">
    <div class="card-body">
        <form id="formFiltroRelatorios" class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label for="data_inicio" class="form-label">Período de:</label>
                <input type="date" class="form-control" id="data_inicio" name="inicio" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="col-12 col-md-5">
                <label for="data_fim" class="form-label">Até:</label>
                <input type="date" class="form-control" id="data_fim" name="fim" value="<?php echo $data_fim; ?>">
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-danger">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="card card-custom"><div class="card-body text-center"><h6 class="text-white-50">Receitas no Período</h6><p class="display-6 fw-bold text-success valor-sensivel">R$ <?php echo number_format($kpis['total_receitas'], 2, ',', '.'); ?></p></div></div></div>
    <div class="col-md-3"><div class="card card-custom"><div class="card-body text-center"><h6 class="text-white-50">Despesas no Período</h6><p class="display-6 fw-bold text-danger valor-sensivel">R$ <?php echo number_format($kpis['total_despesas'], 2, ',', '.'); ?></p></div></div></div>
    <div class="col-md-3"><div class="card card-custom"><div class="card-body text-center"><h6 class="text-white-50">Saldo do Período</h6><p class="display-6 fw-bold <?php echo ($kpis['saldo'] >= 0) ? 'text-primary' : 'text-danger'; ?> valor-sensivel">R$ <?php echo number_format($kpis['saldo'], 2, ',', '.'); ?></p></div></div></div>
    <div class="col-md-3"><div class="card card-custom"><div class="card-body text-center"><h6 class="text-white-50">Gasto Médio por Dia</h6><p class="display-6 fw-bold text-warning valor-sensivel">R$ <?php echo number_format($kpis['gasto_medio_diario'], 2, ',', '.'); ?></p></div></div></div>
</div>

<div class="card card-custom mb-4">
    <div class="card-body p-4">
        <h4 class="card-title">Evolução Financeira no Período</h4>
        <div class="chart-container">
            <canvas id="lineChart"></canvas>
        </div>
    </div>
</div>

<div class="card card-custom">
    <div class="card-body">
        <h4 class="card-title p-4">Transações no Período</h4>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="d-none d-md-table-header-group"><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="text-end">Valor (R$)</th></tr></thead>
                <tbody>
                    <?php if (empty($transacoes_filtradas)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma transação encontrada para o período selecionado.</td></tr>
                    <?php else: foreach ($transacoes_filtradas as $t): ?>
                        <tr>
                            <td data-label="Data" class="d-md-table-cell"><?php echo date('d/m/Y', strtotime($t['data_transacao'])); ?></td>
                            <td data-label="Descrição" class="d-md-table-cell"><?php echo htmlspecialchars($t['descricao']); ?></td>
                            <td data-label="Categoria" class="d-md-table-cell"><span class="badge bg-secondary"><?php echo htmlspecialchars($t['nome_categoria'] ?? 'Sem Categoria'); ?></span></td>
                            <td data-label="Valor" class="text-end fw-bold font-monospace <?php echo ($t['tipo'] == 'receita') ? 'text-success' : 'text-danger'; ?> d-md-table-cell">
                                <?php echo ($t['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($t['valor'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        /* Responsividade específica para tabela de relatórios */
        @media (max-width: 767.98px) {
            .table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 0.75rem;
                background: rgba(255, 255, 255, 0.02);
            }
            
            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border: none;
                text-align: right;
            }
            
            .table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                text-align: left;
                margin-right: 1rem;
                color: rgba(255, 255, 255, 0.7);
            }
        }
        </style>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lineChartCanvas = document.getElementById('lineChart');
    if (lineChartCanvas) {
        new Chart(lineChartCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($lineChartLabels); ?>,
                datasets: [
                    {
                        label: 'Receitas',
                        data: <?php echo json_encode($lineChartDataReceitas); ?>,
                        borderColor: '#00b894',
                        backgroundColor: 'rgba(0, 184, 148, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Despesas',
                        data: <?php echo json_encode($lineChartDataDespesas); ?>,
                        borderColor: '#e50914',
                        backgroundColor: 'rgba(229, 9, 20, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#f5f5f1' } } },
                scales: {
                    y: { grid: { color: 'rgba(255, 255, 255, 0.1)' }, ticks: { color: '#adb5bd' } },
                    x: { grid: { display: false }, ticks: { color: '#adb5bd' } }
                }
            }
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>