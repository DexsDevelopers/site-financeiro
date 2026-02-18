<?php
// orcamento.php (Versão Moderna com Animações e AJAX)

require_once 'templates/header.php';
require_once 'includes/db_connect.php';

// Garante que o nome do mês apareça em português
setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'portuguese');
$mes_atual_str = strftime('%B de %Y');

$mes_atual = date('n');
$ano_atual = date('Y');
$orcamentos_detalhados = [];

try {
    $sql_categorias = "SELECT id, nome FROM categorias WHERE id_usuario = ? AND tipo = 'despesa' ORDER BY nome ASC";
    $stmt_categorias = $pdo->prepare($sql_categorias);
    $stmt_categorias->execute([$userId]);
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_KEY_PAIR);

    $sql_gastos = "SELECT id_categoria, SUM(valor) as total_gasto FROM transacoes WHERE id_usuario = ? AND tipo = 'despesa' AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ? GROUP BY id_categoria";
    $stmt_gastos = $pdo->prepare($sql_gastos);
    $stmt_gastos->execute([$userId, $mes_atual, $ano_atual]);
    $gastos = $stmt_gastos->fetchAll(PDO::FETCH_KEY_PAIR);

    $sql_orcamentos = "SELECT id_categoria, valor FROM orcamentos WHERE id_usuario = ? AND mes = ? AND ano = ?";
    $stmt_orcamentos = $pdo->prepare($sql_orcamentos);
    $stmt_orcamentos->execute([$userId, $mes_atual, $ano_atual]);
    $orcamentos = $stmt_orcamentos->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($categorias as $id_cat => $nome_cat) {
        $gasto_atual = $gastos[$id_cat] ?? 0;
        $orcamento_definido = $orcamentos[$id_cat] ?? 0;
        
        if ($orcamento_definido > 0) {
             $percentual = ($orcamento_definido > 0) ? ($gasto_atual / $orcamento_definido) * 100 : 0;
             $cor_barra = 'bg-success';
             if ($percentual > 75) $cor_barra = 'bg-warning';
             if ($percentual > 100) $cor_barra = 'bg-danger';

             $orcamentos_detalhados[] = [
                'nome' => $nome_cat, 'gasto' => $gasto_atual, 'orcamento' => $orcamento_definido,
                'restante' => $orcamento_definido - $gasto_atual,
                'percentual' => $percentual,
                'cor' => $cor_barra
             ];
        }
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados do orçamento: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Orçamentos de <?php echo ucfirst($mes_atual_str); ?></h1>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalEditarOrcamento">
        <i class="bi bi-pencil-fill me-2"></i>Definir / Editar
    </button>
</div>

<div class="row g-4">
    <?php if(empty($orcamentos_detalhados)): ?>
        <div class="col-12">
            <div class="card card-custom">
                <div class="card-body text-center p-5">
                    <h5 class="text-muted">Você ainda não definiu nenhum orçamento para este mês.</h5>
                    <p class="text-muted mb-3">Clique em "Definir / Editar" para começar a planejar seus gastos.</p>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalEditarOrcamento">Criar meu primeiro orçamento</button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach($orcamentos_detalhados as $index => $orc): ?>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
            <div class="card card-custom h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <h5 class="card-title"><?php echo htmlspecialchars($orc['nome']); ?></h5>
                        <span class="fw-bold <?php echo ($orc['restante'] < 0) ? 'text-danger' : ''; ?>">
                            <?php if($orc['restante'] < 0): ?> Estourado em R$ <?php echo number_format(abs($orc['restante']), 2, ',', '.'); ?>
                            <?php else: ?> Restam R$ <?php echo number_format($orc['restante'], 2, ',', '.'); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $orc['cor']; ?>" 
                             role="progressbar" 
                             style="width: 0%;" 
                             data-value="<?php echo min($orc['percentual'], 100); ?>"
                             aria-valuenow="<?php echo min($orc['percentual'], 100); ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <b class="d-flex justify-content-center w-100">R$ <?php echo number_format($orc['gasto'], 2, ',', '.'); ?> de R$ <?php echo number_format($orc['orcamento'], 2, ',', '.'); ?></b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<div class="modal fade" id="modalEditarOrcamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Definir Orçamentos para <?php echo ucfirst($mes_atual_str); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarOrcamento" action="salvar_orcamento.php" method="POST">
                <div class="modal-body">
                    <p class="text-muted">Preencha os valores mensais para suas categorias de despesa. Deixe em branco ou 0 para não definir/remover um orçamento.</p>
                    <hr>
                    <div class="row">
                    <?php foreach ($categorias as $id_cat => $nome_cat): ?>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text" style="width: 120px;"><?php echo htmlspecialchars($nome_cat); ?></span>
                                <span class="input-group-text">R$</span>
                                <input type="number" step="0.01" min="0" class="form-control" name="orcamento[<?php echo $id_cat; ?>]" value="<?php echo $orcamentos[$id_cat] ?? ''; ?>" placeholder="0,00">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Salvar Orçamentos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    AOS.init({ duration: 800, once: true });

    // --- ANIMAÇÃO DAS BARRAS DE PROGRESSO ---
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressBar = entry.target;
                const finalWidth = progressBar.getAttribute('data-value');
                // Animação suave com GSAP
                gsap.to(progressBar, {
                    width: finalWidth + '%',
                    duration: 1.5,
                    ease: 'power3.out'
                });
                observer.unobserve(progressBar); // Anima apenas uma vez
            }
        });
    }, { threshold: 0.5 }); // Inicia quando 50% da barra estiver visível

    document.querySelectorAll('.progress-bar').forEach(bar => {
        observer.observe(bar);
    });

    // --- LÓGICA AJAX PARA SALVAR ORÇAMENTOS ---
    const formEditarOrcamento = document.getElementById('formEditarOrcamento');
    if (formEditarOrcamento) {
        formEditarOrcamento.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(formEditarOrcamento);
            const button = formEditarOrcamento.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Salvando...';

            fetch('salvar_orcamento.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Sucesso!', data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Erro!', data.message, true);
                }
            })
            .catch(error => showToast('Erro de Rede!', 'Não foi possível se conectar.', true))
            .finally(() => {
                button.disabled = false;
                button.innerHTML = 'Salvar Orçamentos';
                bootstrap.Modal.getInstance(document.getElementById('modalEditarOrcamento')).hide();
            });
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>