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

        <div class="card card-custom transacoes-card">
    <div class="card-header transacoes-header">
        <h4 class="transacoes-titulo"><i class="bi bi-receipt me-2"></i>Transações no Período</h4>
        <span class="transacoes-contador"><?php echo count($transacoes_filtradas); ?> itens</span>
    </div>
    <div class="card-body transacoes-body">
        <div id="tabela-transacoes-relatorio" class="transacoes-lista">
            <?php if (empty($transacoes_filtradas)): ?>
                <div class="transacao-vazia">
                    <i class="bi bi-inbox"></i>
                    <p>Nenhuma transação encontrada</p>
                    <span>Tente ajustar o período selecionado</span>
                </div>
            <?php else: ?>
                <?php foreach ($transacoes_filtradas as $t): ?>
                    <div class="transacao-item <?php echo $t['tipo']; ?>">
                        <div class="transacao-indicador"></div>
                        <div class="transacao-conteudo">
                            <div class="transacao-principal">
                                <span class="transacao-descricao"><?php echo htmlspecialchars($t['descricao']); ?></span>
                                <span class="transacao-valor <?php echo ($t['tipo'] == 'receita') ? 'receita' : 'despesa'; ?>">
                                    <?php echo ($t['tipo'] == 'receita' ? '+' : '-'); ?> R$ <?php echo number_format($t['valor'], 2, ',', '.'); ?>
                                </span>
                            </div>
                            <div class="transacao-detalhes">
                                <span class="transacao-data"><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($t['data_transacao'])); ?></span>
                                <span class="transacao-categoria"><?php echo htmlspecialchars($t['nome_categoria'] ?? 'Sem Categoria'); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
        
        <style>
        /* =========================================== */
        /* DESIGN MODERNO - LISTA DE TRANSAÇÕES */
        /* =========================================== */
        
        .transacoes-card {
            border-radius: 20px !important;
            overflow: hidden !important;
            border: none !important;
            background: rgba(20, 20, 25, 0.9) !important;
            backdrop-filter: blur(20px) !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4) !important;
        }
        
        .transacoes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.1) 0%, rgba(48, 43, 99, 0.1) 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .transacoes-titulo {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
        }
        
        .transacoes-titulo i {
            color: var(--accent-red);
        }
        
        .transacoes-contador {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }
        
        .transacoes-body {
            padding: 1rem !important;
            overflow-x: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .transacoes-lista {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100% !important;
            max-width: 100% !important;
            overflow-x: hidden !important;
            box-sizing: border-box !important;
        }
        
        /* Item de transação */
        .transacao-item {
            display: flex;
            align-items: stretch;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.06);
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .transacao-item:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: translateX(4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Indicador lateral colorido */
        .transacao-indicador {
            width: 4px;
            flex-shrink: 0;
        }
        
        .transacao-item.receita .transacao-indicador {
            background: linear-gradient(180deg, #00b894 0%, #00a885 100%);
        }
        
        .transacao-item.despesa .transacao-indicador {
            background: linear-gradient(180deg, #e50914 0%, #c4080f 100%);
        }
        
        /* Conteúdo da transação */
        .transacao-conteudo {
            flex: 1;
            padding: 1rem 1.25rem;
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
        }
        
        .transacao-principal {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .transacao-descricao {
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            flex: 1;
            min-width: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .transacao-valor {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.1rem;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .transacao-valor.receita {
            color: #00b894;
        }
        
        .transacao-valor.despesa {
            color: #e50914;
        }
        
        .transacao-detalhes {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .transacao-data {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .transacao-data i {
            font-size: 0.75rem;
        }
        
        .transacao-categoria {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }
        
        /* Estado vazio */
        .transacao-vazia {
            text-align: center;
            padding: 3rem 1rem;
            color: rgba(255, 255, 255, 0.4);
        }
        
        .transacao-vazia i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .transacao-vazia p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .transacao-vazia span {
            font-size: 0.9rem;
        }
        
        /* =========================================== */
        /* RESPONSIVO - MOBILE */
        /* =========================================== */
        @media (max-width: 767.98px) {
            .transacoes-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1.25rem;
                text-align: center;
            }
            
            .transacoes-titulo {
                font-size: 1.1rem;
            }
            
            .transacoes-body {
                padding: 0.75rem !important;
            }
            
            .transacao-item {
                flex-direction: column;
            }
            
            .transacao-indicador {
                width: 100%;
                height: 4px;
            }
            
            .transacao-conteudo {
                padding: 1rem;
            }
            
            .transacao-principal {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .transacao-descricao {
                font-size: 0.95rem;
            }
            
            .transacao-valor {
                font-size: 1.25rem;
            }
            
            .transacao-detalhes {
                gap: 0.75rem;
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