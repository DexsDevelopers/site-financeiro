<?php
// compras_futuras_simples.php - Versão simplificada sem bibliotecas externas
require_once 'templates/header.php';

$compras_planejando = [];
$compras_concluidas = [];
$estatisticas = [];

try {
    // Buscar compras
    $stmt = $pdo->prepare("
        SELECT *, 
        CASE 
            WHEN valor_estimado IS NOT NULL THEN valor_estimado 
            ELSE valor_total 
        END as valor_meta,
        CASE 
            WHEN valor_poupado IS NOT NULL THEN valor_poupado 
            ELSE 0 
        END as valor_poupado_atual
        FROM compras_futuras 
        WHERE id_usuario = ? 
        ORDER BY status, ordem ASC, data_criacao DESC
    ");
    $stmt->execute([$userId]);
    $all_compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($all_compras as $compra) {
        if ($compra['status'] == 'planejando') {
            $compras_planejando[] = $compra;
        } else {
            $compras_concluidas[] = $compra;
        }
    }
    
} catch (PDOException $e) { 
    die("Erro ao buscar compras: " . $e->getMessage()); 
}

// Calcular estatísticas
$total_planejando = count($compras_planejando);
$total_concluidas = count($compras_concluidas);
$valor_total_planejando = array_sum(array_column($compras_planejando, 'valor_meta'));
$valor_total_poupado = array_sum(array_column($compras_planejando, 'valor_poupado_atual'));

$estatisticas = [
    'total_planejando' => $total_planejando,
    'total_concluidas' => $total_concluidas,
    'valor_total_planejando' => $valor_total_planejando,
    'valor_total_poupado' => $valor_total_poupado,
    'saldo_mes' => 0
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras Futuras - Simples</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .hero-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        .meta-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: #E50914;
            border: none;
        }
        .btn-primary:hover {
            background: #c4080f;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-4 fw-bold mb-3">
                            <i class="bi bi-cart-check me-3"></i>Compras Futuras
                        </h1>
                        <p class="lead mb-4">Planeje suas compras e alcance seus objetivos financeiros</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalNovaCompra">
                            <i class="bi bi-plus-lg me-2"></i>Nova Meta
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 class="text-primary"><?php echo $estatisticas['total_planejando']; ?></h3>
                    <p class="mb-0">Metas Ativas</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 class="text-success"><?php echo $estatisticas['total_concluidas']; ?></h3>
                    <p class="mb-0">Metas Concluídas</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 class="text-info">R$ <?php echo number_format($estatisticas['valor_total_planejando'], 2, ',', '.'); ?></h3>
                    <p class="mb-0">Valor Total</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 class="text-warning">R$ <?php echo number_format($estatisticas['valor_total_poupado'], 2, ',', '.'); ?></h3>
                    <p class="mb-0">Poupado</p>
                </div>
            </div>
        </div>

        <!-- Metas Ativas -->
        <div class="row">
            <div class="col-12">
                <h4 class="mb-3">Metas Ativas (<?php echo count($compras_planejando); ?>)</h4>
            </div>
        </div>

        <div class="row" id="lista-planejando">
            <?php if (empty($compras_planejando)): ?>
                <div class="col-12">
                    <div class="meta-card text-center">
                        <i class="bi bi-cart-x" style="font-size: 3rem; color: #ccc;"></i>
                        <h5>Nenhuma meta ativa</h5>
                        <p class="text-muted">Crie sua primeira meta de compra para começar!</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaCompra">
                            <i class="bi bi-plus-lg me-2"></i>Criar Primeira Meta
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($compras_planejando as $compra): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="meta-card">
                            <h5><?php echo htmlspecialchars($compra['nome_item']); ?></h5>
                            <p><strong>Valor:</strong> R$ <?php echo number_format($compra['valor_meta'], 2, ',', '.'); ?></p>
                            <p><strong>Poupado:</strong> R$ <?php echo number_format($compra['valor_poupado_atual'], 2, ',', '.'); ?></p>
                            <p><strong>Prioridade:</strong> <?php echo ucfirst($compra['prioridade'] ?? 'Média'); ?></p>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary">Editar</button>
                                <button class="btn btn-sm btn-outline-success">Concluir</button>
                                <button class="btn btn-sm btn-outline-danger">Excluir</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Metas Concluídas -->
        <?php if (!empty($compras_concluidas)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h4 class="mb-3">Metas Concluídas (<?php echo count($compras_concluidas); ?>)</h4>
            </div>
        </div>

        <div class="row" id="lista-concluidas">
            <?php foreach($compras_concluidas as $compra): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="meta-card" style="opacity: 0.8;">
                        <h5 class="text-decoration-line-through"><?php echo htmlspecialchars($compra['nome_item']); ?></h5>
                        <p><strong>Valor:</strong> R$ <?php echo number_format($compra['valor_meta'], 2, ',', '.'); ?></p>
                        <p><strong>Poupado:</strong> R$ <?php echo number_format($compra['valor_poupado_atual'], 2, ',', '.'); ?></p>
                        <span class="badge bg-success">Concluída</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Nova Compra -->
    <div class="modal fade" id="modalNovaCompra" tabindex="-1" aria-labelledby="modalNovaCompraLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNovaCompraLabel">Planejar Nova Compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formNovaCompra">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nome_item" class="form-label">O que você quer comprar?</label>
                            <input type="text" name="nome_item" id="nome_item" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="valor_estimado" class="form-label">Valor Estimado (R$)</label>
                            <input type="number" name="valor_estimado" id="valor_estimado" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="link_referencia" class="form-label">Link de Referência (Opcional)</label>
                            <input type="url" name="link_referencia" id="link_referencia" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página carregada');
            
            const formNovaCompra = document.getElementById('formNovaCompra');
            const modalNovaCompra = new bootstrap.Modal(document.getElementById('modalNovaCompra'));
            
            console.log('Formulário encontrado:', !!formNovaCompra);
            console.log('Modal encontrado:', !!modalNovaCompra);
            
            // Teste de clique no botão
            const btnNovaMeta = document.querySelector('[data-bs-target="#modalNovaCompra"]');
            if (btnNovaMeta) {
                btnNovaMeta.addEventListener('click', function() {
                    console.log('Botão clicado!');
                });
            }
            
            if (formNovaCompra) {
                formNovaCompra.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('Formulário submetido!');
                    
                    const formData = new FormData(formNovaCompra);
                    const data = {
                        nome_item: formData.get('nome_item'),
                        valor_estimado: formData.get('valor_estimado'),
                        link_referencia: formData.get('link_referencia')
                    };
                    
                    console.log('Dados do formulário:', data);
                    
                    // Enviar para o servidor
                    fetch('adicionar_compra_futura.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Resposta recebida:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Dados recebidos:', data);
                        if (data.success) {
                            alert('Meta criada com sucesso!');
                            window.location.reload();
                        } else {
                            alert('Erro: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição:', error);
                        alert('Erro na requisição: ' + error.message);
                    });
                });
            } else {
                console.error('Formulário não encontrado!');
            }
        });
    </script>
</body>
</html>
