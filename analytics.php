<?php
// analytics.php - Sistema de Analytics Avançado

require_once 'templates/header.php';

// Parâmetros de filtro
$mes_selecionado = $_GET['mes'] ?? date('n');
$ano_selecionado = $_GET['ano'] ?? date('Y');

// Inicializar variáveis
$dados_analytics = [];
$insights = [];
$recomendacoes = [];

try {
    // 1. Dados financeiros do período
    $stmt_financeiro = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as total_receitas,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as total_despesas,
            COUNT(CASE WHEN tipo = 'receita' THEN 1 END) as qtd_receitas,
            COUNT(CASE WHEN tipo = 'despesa' THEN 1 END) as qtd_despesas
        FROM transacoes 
        WHERE id_usuario = ? AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?
    ");
    $stmt_financeiro->execute([$userId, $mes_selecionado, $ano_selecionado]);
    $dados_financeiros = $stmt_financeiro->fetch(PDO::FETCH_ASSOC);

    // 2. Tendências dos últimos 6 meses
    $stmt_tendencias = $pdo->prepare("
        SELECT 
            MONTH(data_transacao) as mes,
            YEAR(data_transacao) as ano,
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) as receitas,
            SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) as despesas,
            (SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) - 
             SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END)) as saldo
        FROM transacoes 
        WHERE id_usuario = ? 
        AND data_transacao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(data_transacao), MONTH(data_transacao)
        ORDER BY ano, mes
    ");
    $stmt_tendencias->execute([$userId]);
    $tendencias = $stmt_tendencias->fetchAll(PDO::FETCH_ASSOC);

    // 3. Análise por categoria
    $stmt_categorias = $pdo->prepare("
        SELECT 
            c.nome as categoria,
            SUM(t.valor) as total,
            COUNT(t.id) as quantidade,
            AVG(t.valor) as media,
            (SUM(t.valor) / (SELECT SUM(valor) FROM transacoes WHERE id_usuario = ? AND tipo = 'despesa' AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?)) * 100 as percentual
        FROM categorias c
        JOIN transacoes t ON c.id = t.id_categoria
        WHERE c.id_usuario = ? AND t.tipo = 'despesa' 
        AND MONTH(t.data_transacao) = ? AND YEAR(t.data_transacao) = ?
        GROUP BY c.id, c.nome
        ORDER BY total DESC
    ");
    $stmt_categorias->execute([$userId, $mes_selecionado, $ano_selecionado, $userId, $mes_selecionado, $ano_selecionado]);
    $analise_categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

    // 4. Padrões de gastos
    $stmt_padroes = $pdo->prepare("
        SELECT 
            DAYOFWEEK(data_transacao) as dia_semana,
            HOUR(data_transacao) as hora,
            AVG(valor) as media_gasto,
            COUNT(*) as frequencia
        FROM transacoes 
        WHERE id_usuario = ? AND tipo = 'despesa' 
        AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?
        GROUP BY DAYOFWEEK(data_transacao), HOUR(data_transacao)
        ORDER BY frequencia DESC
    ");
    $stmt_padroes->execute([$userId, $mes_selecionado, $ano_selecionado]);
    $padroes = $stmt_padroes->fetchAll(PDO::FETCH_ASSOC);

    // 5. Gerar insights
    $insights = gerarInsights($dados_financeiros, $analise_categorias, $tendencias);
    $recomendacoes = gerarRecomendacoes($dados_financeiros, $analise_categorias, $padroes);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

function gerarInsights($dados, $categorias, $tendencias) {
    $insights = [];
    
    // Insight sobre saldo
    $saldo = $dados['total_receitas'] - $dados['total_despesas'];
    if ($saldo > 0) {
        $insights[] = [
            'tipo' => 'success',
            'titulo' => 'Saldo Positivo',
            'descricao' => "Seu saldo este mês foi de R$ " . number_format($saldo, 2, ',', '.') . ". Parabéns!",
            'icone' => 'bi-check-circle'
        ];
    } else {
        $insights[] = [
            'tipo' => 'warning',
            'titulo' => 'Atenção ao Saldo',
            'descricao' => "Seu saldo este mês foi negativo: R$ " . number_format($saldo, 2, ',', '.'),
            'icone' => 'bi-exclamation-triangle'
        ];
    }
    
    // Insight sobre categoria com maior gasto
    if (!empty($categorias)) {
        $maior_categoria = $categorias[0];
        $insights[] = [
            'tipo' => 'info',
            'titulo' => 'Maior Categoria de Gasto',
            'descricao' => "Você gastou R$ " . number_format($maior_categoria['total'], 2, ',', '.') . " em " . $maior_categoria['categoria'] . " (" . number_format($maior_categoria['percentual'], 1) . "% do total)",
            'icone' => 'bi-pie-chart'
        ];
    }
    
    return $insights;
}

function gerarRecomendacoes($dados, $categorias, $padroes) {
    $recomendacoes = [];
    
    // Recomendação baseada em gastos altos
    if ($dados['total_despesas'] > $dados['total_receitas'] * 0.8) {
        $recomendacoes[] = [
            'tipo' => 'economia',
            'titulo' => 'Controle de Gastos',
            'descricao' => 'Seus gastos representam mais de 80% da sua receita. Considere revisar seus gastos.',
            'acao' => 'Revisar Orçamento'
        ];
    }
    
    // Recomendação baseada em categorias
    foreach ($categorias as $cat) {
        if ($cat['percentual'] > 30) {
            $recomendacoes[] = [
                'tipo' => 'categoria',
                'titulo' => 'Gasto Concentrado',
                'descricao' => "Você está gastando " . number_format($cat['percentual'], 1) . "% do seu orçamento em " . $cat['categoria'] . ". Considere diversificar.",
                'acao' => 'Analisar Categoria'
            ];
        }
    }
    
    return $recomendacoes;
}

function getDiaSemana($numero) {
    $dias = ['', 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    return $dias[$numero] ?? 'Desconhecido';
}
?>

<style>
.analytics-header {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-300) 100%);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
}

/* Responsividade para analytics.php */
@media (max-width: 767.98px) {
    .analytics-header {
        padding: 1.5rem 1rem;
        margin-bottom: 1.5rem;
    }
    
    .analytics-header .h2 {
        font-size: 1.5rem !important;
    }
    
    .analytics-header .btn-group {
        flex-direction: column;
        width: 100%;
        margin-top: 1rem;
    }
    
    .analytics-header .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .filter-panel {
        padding: 1rem !important;
    }
    
    .filter-panel .row {
        flex-direction: column;
    }
    
    .filter-panel .col-md-6 {
        margin-bottom: 1rem;
    }
    
    .metric-card {
        padding: 1rem !important;
    }
    
    .metric-value {
        font-size: 1.75rem !important;
    }
    
    .chart-container {
        padding: 1rem !important;
    }
    
    .insight-card,
    .recommendation-item {
        padding: 1rem !important;
    }
    
    .recommendation-item {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .recommendation-icon {
        margin-bottom: 0.5rem;
    }
}

.metric-card {
    background: var(--card-bg-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-primary);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: var(--accent);
}

.metric-card:hover::before {
    opacity: 1;
}

.metric-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--accent);
    margin-bottom: 0.5rem;
}

.metric-label {
    color: var(--text-400);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.metric-change {
    font-size: 0.8rem;
    font-weight: 500;
}

.insight-card {
    background: var(--card-bg-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid var(--accent);
}

.insight-card.success {
    border-left-color: #28a745;
}

.insight-card.warning {
    border-left-color: #ffc107;
}

.insight-card.info {
    border-left-color: #17a2b8;
}

.chart-container {
    background: var(--card-bg-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.recommendation-item {
    background: var(--bg-700);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.recommendation-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.recommendation-icon.economia {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.recommendation-icon.categoria {
    background: rgba(23, 162, 184, 0.2);
    color: #17a2b8;
}

.filter-panel {
    background: var(--card-bg-light);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
</style>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="analytics-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h2 mb-2">
                    <i class="bi bi-graph-up-arrow me-2"></i>Analytics Financeiro
                </h1>
                <p class="mb-0 opacity-75">Análise profunda dos seus hábitos financeiros e insights inteligentes</p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <button class="btn btn-light" onclick="exportarRelatorio()">
                        <i class="bi bi-download me-2"></i>Exportar
                    </button>
                    <button class="btn btn-outline-light" onclick="atualizarDados()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Atualizar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filter-panel">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0">Filtros de Período</h6>
            </div>
            <div class="col-md-6">
                <div class="row g-2">
                    <div class="col-md-6">
                        <select class="form-select form-select-sm" id="selectMes" onchange="atualizarPeriodo()">
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $mes_selecionado ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select form-select-sm" id="selectAno" onchange="atualizarPeriodo()">
                            <?php for($ano = date('Y') - 2; $ano <= date('Y'); $ano++): ?>
                                <option value="<?php echo $ano; ?>" <?php echo $ano == $ano_selecionado ? 'selected' : ''; ?>>
                                    <?php echo $ano; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Métricas Principais -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="metric-value">R$ <?php echo number_format($dados_financeiros['total_receitas'], 2, ',', '.'); ?></div>
                <div class="metric-label">Receitas</div>
                <div class="metric-change text-success">
                    <i class="bi bi-arrow-up"></i> <?php echo $dados_financeiros['qtd_receitas']; ?> transações
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="metric-value">R$ <?php echo number_format($dados_financeiros['total_despesas'], 2, ',', '.'); ?></div>
                <div class="metric-label">Despesas</div>
                <div class="metric-change text-danger">
                    <i class="bi bi-arrow-down"></i> <?php echo $dados_financeiros['qtd_despesas']; ?> transações
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="metric-value">R$ <?php echo number_format($dados_financeiros['total_receitas'] - $dados_financeiros['total_despesas'], 2, ',', '.'); ?></div>
                <div class="metric-label">Saldo</div>
                <div class="metric-change <?php echo ($dados_financeiros['total_receitas'] - $dados_financeiros['total_despesas']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="bi bi-<?php echo ($dados_financeiros['total_receitas'] - $dados_financeiros['total_despesas']) >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo ($dados_financeiros['total_receitas'] - $dados_financeiros['total_despesas']) >= 0 ? 'Positivo' : 'Negativo'; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="metric-value"><?php echo count($analise_categorias); ?></div>
                <div class="metric-label">Categorias</div>
                <div class="metric-change text-info">
                    <i class="bi bi-tags"></i> Ativas
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-4">
            <div class="chart-container">
                <h5 class="mb-3">Tendências dos Últimos 6 Meses</h5>
                <canvas id="tendenciasChart" height="100"></canvas>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="chart-container">
                <h5 class="mb-3">Distribuição por Categoria</h5>
                <canvas id="categoriasChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Insights e Recomendações -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="chart-container">
                <h5 class="mb-3">
                    <i class="bi bi-lightbulb me-2"></i>Insights Inteligentes
                </h5>
                <?php if (empty($insights)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-info-circle display-4"></i>
                        <p class="mt-2">Nenhum insight disponível para este período.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($insights as $insight): ?>
                        <div class="insight-card <?php echo $insight['tipo']; ?>">
                            <div class="d-flex align-items-start">
                                <i class="bi <?php echo $insight['icone']; ?> me-3 fs-4"></i>
                                <div>
                                    <h6 class="mb-1"><?php echo $insight['titulo']; ?></h6>
                                    <p class="mb-0 text-muted"><?php echo $insight['descricao']; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="chart-container">
                <h5 class="mb-3">
                    <i class="bi bi-star me-2"></i>Recomendações
                </h5>
                <?php if (empty($recomendacoes)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle display-4"></i>
                        <p class="mt-2">Suas finanças estão em ordem! Continue assim.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recomendacoes as $rec): ?>
                        <div class="recommendation-item">
                            <div class="recommendation-icon <?php echo $rec['tipo']; ?>">
                                <i class="bi bi-<?php echo $rec['tipo'] === 'economia' ? 'piggy-bank' : 'pie-chart'; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo $rec['titulo']; ?></h6>
                                <p class="mb-1 text-muted small"><?php echo $rec['descricao']; ?></p>
                                <button class="btn btn-sm btn-outline-primary"><?php echo $rec['acao']; ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dados para os gráficos
const tendenciasData = <?php echo json_encode($tendencias); ?>;
const categoriasData = <?php echo json_encode($analise_categorias); ?>;

// Gráfico de Tendências
const ctxTendencias = document.getElementById('tendenciasChart').getContext('2d');
new Chart(ctxTendencias, {
    type: 'line',
    data: {
        labels: tendenciasData.map(item => {
            const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            return meses[item.mes - 1] + '/' + item.ano;
        }),
        datasets: [{
            label: 'Receitas',
            data: tendenciasData.map(item => item.receitas),
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4
        }, {
            label: 'Despesas',
            data: tendenciasData.map(item => item.despesas),
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4
        }, {
            label: 'Saldo',
            data: tendenciasData.map(item => item.saldo),
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: '#f5f5f1'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#aaa',
                    callback: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            },
            x: {
                ticks: {
                    color: '#aaa'
                },
                grid: {
                    color: 'rgba(255, 255, 255, 0.1)'
                }
            }
        }
    }
});

// Gráfico de Categorias
const ctxCategorias = document.getElementById('categoriasChart').getContext('2d');
new Chart(ctxCategorias, {
    type: 'doughnut',
    data: {
        labels: categoriasData.map(item => item.categoria),
        datasets: [{
            data: categoriasData.map(item => item.total),
            backgroundColor: [
                '#e50914', '#ff4d55', '#ff6b35', '#ff8e53', '#6f42c1', '#8e44ad',
                '#28a745', '#20c997', '#17a2b8', '#6c757d', '#fd7e14', '#e83e8c'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#f5f5f1',
                    padding: 20
                }
            }
        }
    }
});

function atualizarPeriodo() {
    const mes = document.getElementById('selectMes').value;
    const ano = document.getElementById('selectAno').value;
    window.location.href = `analytics.php?mes=${mes}&ano=${ano}`;
}

function exportarRelatorio() {
    // Implementar exportação de relatório
    alert('Funcionalidade de exportação em desenvolvimento');
}

function atualizarDados() {
    window.location.reload();
}
</script>

<?php require_once 'templates/footer.php'; ?>
