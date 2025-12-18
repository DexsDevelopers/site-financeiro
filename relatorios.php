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
        <div class="table-responsive" id="tabela-transacoes-relatorio">
            <table class="table table-hover align-middle">
                <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="text-end">Valor (R$)</th></tr></thead>
                <tbody>
                    <?php if (empty($transacoes_filtradas)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma transação encontrada para o período selecionado.</td></tr>
                    <?php else: foreach ($transacoes_filtradas as $t): ?>
                        <tr>
                            <td data-label="Data"><?php echo date('d/m/Y', strtotime($t['data_transacao'])); ?></td>
                            <td data-label="Descrição"><?php echo htmlspecialchars($t['descricao']); ?></td>
                            <td data-label="Categoria"><span class="badge bg-secondary"><?php echo htmlspecialchars($t['nome_categoria'] ?? 'Sem Categoria'); ?></span></td>
                            <td data-label="Valor" class="text-end fw-bold font-monospace <?php echo ($t['tipo'] == 'receita') ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($t['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($t['valor'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        /* Garantir que a tabela seja sempre visível */
        .table-responsive {
            width: 100%;
            display: block;
        }
        
        /* Desktop: permite scroll horizontal se necessário */
        @media (min-width: 768px) {
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        .table {
            width: 100%;
            margin-bottom: 0;
            color: var(--text-primary);
            border-collapse: collapse;
        }
        
        .table thead th {
            border-bottom: 2px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
            padding: 1rem;
        }
        
        .table tbody {
            display: table-row-group;
        }
        
        .table tbody tr {
            display: table-row;
        }
        
        .table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            display: table-cell;
        }
        
        .table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        /* Responsividade específica para tabela de relatórios */
        @media (max-width: 767.98px) {
            /* Garantir que o card não cause overflow */
            .card-body {
                padding: 1rem !important;
                overflow-x: hidden !important;
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            
            .table-responsive {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
                overflow-y: visible !important;
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            .table {
                display: block !important;
                width: 100% !important;
                border-collapse: separate;
                border-spacing: 0;
                table-layout: fixed;
            }
            
            .table thead {
                display: none !important;
            }
            
            .table tbody {
                display: block !important;
                width: 100% !important;
            }
            
            .table tbody tr {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin-bottom: 1rem;
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 12px;
                padding: 0.875rem;
                background: rgba(255, 255, 255, 0.03);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                box-sizing: border-box;
                overflow: hidden;
            }
            
            .table tbody td {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                padding: 0.5rem 0 !important;
                border: none !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
                box-sizing: border-box;
                word-wrap: break-word;
                overflow-wrap: break-word;
                word-break: break-word;
            }
            
            .table tbody td:last-child {
                border-bottom: none !important;
                padding-bottom: 0 !important;
            }
            
            .table tbody td:first-child {
                padding-top: 0 !important;
            }
            
            .table tbody td::before {
                content: attr(data-label) ": ";
                display: block;
                font-weight: 600;
                font-size: 0.85rem;
                margin-bottom: 0.375rem;
                color: rgba(255, 255, 255, 0.7);
            }
            
            /* Conteúdo sempre abaixo do label */
            .table tbody td > *:not(::before) {
                display: block;
                width: 100%;
                text-align: left;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            /* Badges e outros elementos dentro das células */
            .table tbody td .badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
                white-space: normal;
                word-break: break-word;
                display: inline-block;
                max-width: 100%;
                margin-top: 0.25rem;
            }
            
            /* Valores monetários */
            .table tbody td.font-monospace {
                font-size: 1rem;
                font-weight: 700;
                white-space: normal;
                word-break: break-word;
                text-align: left;
                display: block;
                margin-top: 0.25rem;
            }
            
            /* Garantir que valores fiquem alinhados à direita quando apropriado */
            .table tbody td[data-label="Valor"] {
                text-align: left;
            }
            
            .table tbody td[data-label="Valor"] .font-monospace {
                text-align: left;
            }
            
            /* Remover padding extra da última célula */
            .table tbody td:last-child {
                padding-bottom: 0 !important;
                border-bottom: none !important;
            }
            
            /* Primeira célula sem padding extra no topo */
            .table tbody tr td:first-child {
                padding-top: 0 !important;
            }
            
            /* Garantir que nada ultrapasse */
            .table tbody td * {
                max-width: 100%;
                box-sizing: border-box;
            }
            
            /* Garantir que células vazias também apareçam */
            .table tbody tr:empty {
                display: none !important;
            }
            
            /* Forçar visibilidade da tabela de transações */
            #tabela-transacoes-relatorio {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                overflow: visible !important;
            }
            
            #tabela-transacoes-relatorio .table {
                display: block !important;
                visibility: visible !important;
                overflow: visible !important;
            }
            
            #tabela-transacoes-relatorio .table tbody {
                display: block !important;
                visibility: visible !important;
                overflow: visible !important;
            }
            
            #tabela-transacoes-relatorio .table tbody tr {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                overflow: visible !important;
            }
        }
        
        /* Garantir visibilidade em desktop */
        @media (min-width: 768px) {
            .table {
                display: table;
            }
            
            .table tbody {
                display: table-row-group;
            }
            
            .table tbody tr {
                display: table-row;
            }
            
            .table tbody td {
                display: table-cell !important;
            }
            
            .table thead {
                display: table-header-group !important;
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
    
    // Garantir que a tabela seja visível no mobile
    function garantirVisibilidadeTabela() {
        const tabela = document.getElementById('tabela-transacoes-relatorio');
        if (tabela) {
            const linhas = tabela.querySelectorAll('tbody tr');
            linhas.forEach(linha => {
                linha.style.display = 'block';
                linha.style.visibility = 'visible';
                linha.style.opacity = '1';
            });
        }
    }
    
    // Executar após o carregamento
    garantirVisibilidadeTabela();
    
    // Executar também após um pequeno delay para garantir
    setTimeout(garantirVisibilidadeTabela, 100);
});
</script>

<?php
require_once 'templates/footer.php';
?>