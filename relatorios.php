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

        <div class="card card-custom" style="overflow-x: hidden; width: 100%; max-width: 100%; box-sizing: border-box;">
    <div class="card-body" style="overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; padding: 1rem !important;">
        <h4 class="card-title p-4" style="word-wrap: break-word; overflow-wrap: break-word;">Transações no Período</h4>
        <div class="table-responsive" id="tabela-transacoes-relatorio" style="overflow-x: hidden !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important;">
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
        /* =========================================== */
        /* SOBRESCREVER CSS GLOBAL - TABELA RELATÓRIOS */
        /* =========================================== */
        
        /* Forçar largura máxima e sem overflow no container específico */
        #tabela-transacoes-relatorio {
            width: 100% !important;
            max-width: 100% !important;
            display: block !important;
            overflow-x: hidden !important;
            overflow-y: visible !important;
            box-sizing: border-box !important;
        }
        
        /* Desktop: permite scroll horizontal se necessário */
        @media (min-width: 768px) {
            #tabela-transacoes-relatorio {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        /* =========================================== */
        /* ESTILOS DESKTOP - TABELA ULTRA MODERNA */
        /* =========================================== */
        
        /* Tabela base com design premium */
        #tabela-transacoes-relatorio .table {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            margin-bottom: 0;
            color: var(--text-primary);
            border-collapse: separate;
            border-spacing: 0 0.5rem;
            background: transparent;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        /* Cabeçalho premium com design elegante */
        #tabela-transacoes-relatorio .table thead {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.15) 0%, rgba(48, 43, 99, 0.15) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 16px 16px 0 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        #tabela-transacoes-relatorio .table thead th {
            border: none;
            border-bottom: 3px solid var(--accent-red);
            color: #ffffff;
            font-weight: 700;
            padding: 1.5rem 1.25rem;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1.2px;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        #tabela-transacoes-relatorio .table thead th:first-child {
            border-top-left-radius: 16px;
            padding-left: 1.5rem;
        }
        
        #tabela-transacoes-relatorio .table thead th:last-child {
            border-top-right-radius: 16px;
            padding-right: 1.5rem;
        }
        
        /* Linhas da tabela com design premium */
        #tabela-transacoes-relatorio .table tbody {
            display: table-row-group;
        }
        
        #tabela-transacoes-relatorio .table tbody tr {
            display: table-row;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        #tabela-transacoes-relatorio .table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }
        
        #tabela-transacoes-relatorio .table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }
        
        #tabela-transacoes-relatorio .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.12) 0%, rgba(229, 9, 20, 0.06) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(229, 9, 20, 0.3), 0 4px 12px rgba(0, 0, 0, 0.2);
            border-left: 4px solid var(--accent-red);
        }
        
        /* Células premium com tipografia melhorada */
        #tabela-transacoes-relatorio .table tbody td {
            padding: 1.5rem 1.25rem;
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            vertical-align: middle;
            display: table-cell;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.95);
            font-family: 'Poppins', sans-serif;
        }
        
        #tabela-transacoes-relatorio .table tbody td:first-child {
            padding-left: 1.5rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
        }
        
        #tabela-transacoes-relatorio .table tbody td:last-child {
            padding-right: 1.5rem;
        }
        
        /* Badge de categoria premium */
        #tabela-transacoes-relatorio .table tbody td .badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        #tabela-transacoes-relatorio .table tbody td .badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        /* Valores monetários premium */
        #tabela-transacoes-relatorio .table tbody td.font-monospace {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 0.8px;
            font-family: 'Roboto Mono', 'Courier New', monospace;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* Alternância de cores elegante */
        #tabela-transacoes-relatorio .table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.04);
        }
        
        #tabela-transacoes-relatorio .table tbody tr:nth-child(even):hover {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.15) 0%, rgba(229, 9, 20, 0.08) 100%);
        }
        
        /* Descrições com melhor tipografia */
        #tabela-transacoes-relatorio .table tbody td[data-label="Descrição"],
        #tabela-transacoes-relatorio .table tbody td:nth-child(2) {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.05rem;
        }
        
        /* Responsividade específica para tabela de relatórios - MOBILE */
        @media (max-width: 767.98px) {
            /* SOBRESCREVER TODOS OS CSS GLOBAIS */
            /* Container principal - SEM SCROLL HORIZONTAL */
            #tabela-transacoes-relatorio {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                overflow-x: hidden !important;
                overflow-y: visible !important;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                -webkit-overflow-scrolling: auto !important;
            }
            
            /* SOBRESCREVER min-width: 600px dos CSS globais */
            #tabela-transacoes-relatorio .table {
                min-width: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            /* Tabela como container */
            #tabela-transacoes-relatorio .table {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                border-collapse: separate !important;
                border-spacing: 0 !important;
                overflow: visible !important;
            }
            
            /* Esconder cabeçalho */
            #tabela-transacoes-relatorio .table thead {
                display: none !important;
            }
            
            /* Body como container */
            #tabela-transacoes-relatorio .table tbody {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            /* Cards premium no mobile */
            #tabela-transacoes-relatorio .table tbody tr {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin-bottom: 1.5rem !important;
                border: 1px solid rgba(255, 255, 255, 0.12) !important;
                border-left: 5px solid var(--accent-red) !important;
                border-radius: 16px !important;
                padding: 1.5rem !important;
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%) !important;
                backdrop-filter: blur(10px) !important;
                -webkit-backdrop-filter: blur(10px) !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 4px 12px rgba(229, 9, 20, 0.15) !important;
                box-sizing: border-box !important;
                overflow: visible !important;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            
            #tabela-transacoes-relatorio .table tbody tr:active {
                transform: scale(0.97) !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
            }
            
            /* Células premium no mobile */
            #tabela-transacoes-relatorio .table tbody td {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                padding: 1rem 0 !important;
                border: none !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
                box-sizing: border-box !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                word-break: break-word !important;
                overflow: hidden !important;
                hyphens: auto !important;
                font-family: 'Poppins', sans-serif !important;
            }
            
            #tabela-transacoes-relatorio .table tbody tr td:first-child {
                padding-top: 0 !important;
                border-top: none !important;
            }
            
            #tabela-transacoes-relatorio .table tbody td:last-child {
                border-bottom: none !important;
                padding-bottom: 0 !important;
            }
            
            /* Labels premium */
            #tabela-transacoes-relatorio .table tbody td::before {
                content: attr(data-label);
                display: block !important;
                font-weight: 700 !important;
                font-size: 0.7rem !important;
                margin-bottom: 0.75rem !important;
                color: var(--accent-red) !important;
                text-transform: uppercase !important;
                letter-spacing: 1.2px !important;
                opacity: 1 !important;
                font-family: 'Poppins', sans-serif !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
            }
            
            /* Conteúdo abaixo */
            #tabela-transacoes-relatorio .table tbody td > * {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                text-align: left !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                word-break: break-word !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                hyphens: auto !important;
            }
            
            /* Forçar quebra em textos longos */
            #tabela-transacoes-relatorio .table tbody td,
            #tabela-transacoes-relatorio .table tbody td * {
                white-space: normal !important;
                overflow-wrap: anywhere !important;
            }
            
            /* Badges premium */
            #tabela-transacoes-relatorio .table tbody td .badge {
                display: inline-block !important;
                font-size: 0.7rem !important;
                padding: 0.5rem 1rem !important;
                white-space: normal !important;
                word-break: break-word !important;
                max-width: 100% !important;
                border-radius: 25px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.8px !important;
                box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25) !important;
                font-family: 'Poppins', sans-serif !important;
                transition: all 0.3s ease !important;
            }
            
            /* Valores monetários premium */
            #tabela-transacoes-relatorio .table tbody td.font-monospace {
                display: block !important;
                font-size: 1.3rem !important;
                font-weight: 800 !important;
                white-space: normal !important;
                word-break: break-word !important;
                letter-spacing: 0.8px !important;
                margin-top: 0.5rem !important;
                font-family: 'Roboto Mono', 'Courier New', monospace !important;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
            }
            
            /* Descrições premium */
            #tabela-transacoes-relatorio .table tbody td[data-label="Descrição"],
            #tabela-transacoes-relatorio .table tbody td:nth-child(2) {
                font-size: 1.1rem !important;
                line-height: 1.6 !important;
                font-weight: 500 !important;
                color: rgba(255, 255, 255, 0.95) !important;
                margin-top: 0.5rem !important;
            }
            
            /* Data com estilo premium */
            #tabela-transacoes-relatorio .table tbody td[data-label="Data"] {
                font-weight: 500 !important;
                color: rgba(255, 255, 255, 0.8) !important;
                font-size: 0.95rem !important;
            }
            
            /* Card body sem overflow */
            .card-body {
                overflow-x: hidden !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            
            /* Forçar que todos os elementos respeitem a largura */
            #tabela-transacoes-relatorio * {
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            
            /* Valores monetários com quebra forçada */
            #tabela-transacoes-relatorio .table tbody td.font-monospace {
                white-space: normal !important;
                overflow-wrap: anywhere !important;
                word-break: break-all !important;
            }
            
            /* Descrições longas */
            #tabela-transacoes-relatorio .table tbody td[data-label="Descrição"] {
                overflow-wrap: anywhere !important;
                word-break: break-word !important;
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
    
    // SOBRESCREVER CSS GLOBAL - Forçar estilos corretos
    function corrigirTabelaMobile() {
        const tabela = document.getElementById('tabela-transacoes-relatorio');
        if (tabela && window.innerWidth <= 767.98) {
            // Forçar estilos no container
            tabela.style.overflowX = 'hidden';
            tabela.style.overflowY = 'visible';
            tabela.style.width = '100%';
            tabela.style.maxWidth = '100%';
            tabela.style.minWidth = '0';
            
            // Forçar estilos na tabela
            const tableEl = tabela.querySelector('.table');
            if (tableEl) {
                tableEl.style.minWidth = '0';
                tableEl.style.width = '100%';
                tableEl.style.maxWidth = '100%';
            }
            
            // Forçar estilos nas células
            const cells = tabela.querySelectorAll('tbody td');
            cells.forEach(cell => {
                cell.style.maxWidth = '100%';
                cell.style.wordWrap = 'break-word';
                cell.style.overflowWrap = 'break-word';
                cell.style.wordBreak = 'break-word';
                cell.style.overflow = 'hidden';
            });
        }
    }
    
    // Executar imediatamente
    corrigirTabelaMobile();
    
    // Executar após carregamento completo
    window.addEventListener('load', corrigirTabelaMobile);
    
    // Executar quando a tela redimensionar
    window.addEventListener('resize', corrigirTabelaMobile);
    
    // Executar após um delay para garantir
    setTimeout(corrigirTabelaMobile, 100);
    setTimeout(corrigirTabelaMobile, 500);
});
</script>

<?php
require_once 'templates/footer.php';
?>