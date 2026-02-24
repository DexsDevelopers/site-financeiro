<?php
// /admin/admin_empresas.php
require_once 'header_admin.php';

// Busca estatísticas
$est = ['total' => 0, 'novas_30d' => 0, 'donos_unicos' => 0];
try {
    $stmtEst = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM ge_empresas) as total,
        (SELECT COUNT(*) FROM ge_empresas WHERE data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as novas_30d,
        (SELECT COUNT(DISTINCT id_usuario) FROM ge_empresas) as donos_unicos
    ");
    if ($stmtEst) $est = $stmtEst->fetch();
} catch (PDOException $e) {
    // Tabelas podem não existir ainda
}

// Busca empresas e seus donos
$empresas = [];
try {
    $stmtEmp = $pdo->query("
        SELECT e.*, u.nome_completo as dono_nome, u.usuario as dono_user
        FROM ge_empresas e
        JOIN usuarios u ON e.id_usuario = u.id
        ORDER BY e.data_criacao DESC
    ");
    if ($stmtEmp) $empresas = $stmtEmp->fetchAll();
} catch (PDOException $e) {
    // Tabelas podem não existir ainda
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1"><i class="bi bi-building-fill me-2 text-danger"></i>Gerenciamento de Empresas</h1>
        <p class="text-muted mb-0">Visualize e supervisione todas as empresas cadastradas no sistema</p>
    </div>
    <div class="d-flex gap-2">
        <a href="db_migration_ge.php" class="btn btn-warning">
            <i class="bi bi-database-fill-add me-2"></i>Migrar Tabelas
        </a>
    </div>
</div>

<!-- Estatísticas -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-body">
                <i class="bi bi-building fs-2 mb-2" style="color: var(--admin-accent);"></i>
                <h5 class="mb-1"><?php echo $est['total']; ?></h5>
                <small class="text-muted">Total de Empresas</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-body">
                <i class="bi bi-plus-circle fs-2 mb-2" style="color: #00b894;"></i>
                <h5 class="mb-1"><?php echo $est['novas_30d']; ?></h5>
                <small class="text-muted">Novas (30 dias)</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card text-center">
            <div class="card-body">
                <i class="bi bi-people-fill fs-2 mb-2" style="color: #0984e3;"></i>
                <h5 class="mb-1"><?php echo $est['donos_unicos']; ?></h5>
                <small class="text-muted">Usuários com Empresas</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabela de Empresas -->
<div class="admin-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Lista Global de Empresas</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Segmento</th>
                        <th>Proprietário</th>
                        <th>Data Cadastro</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($empresas)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">Nenhuma empresa cadastrada ainda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($empresas as $emp): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="bi bi-building text-white"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block text-white"><?php echo htmlspecialchars($emp['nome']); ?></strong>
                                            <small class="text-muted">ID: #<?php echo $emp['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($emp['segmento'] ?: 'Geral'); ?></span>
                                </td>
                                <td>
                                    <div class="small">
                                        <div class="text-white"><?php echo htmlspecialchars($emp['dono_nome']); ?></div>
                                        <code class="text-info">@<?php echo htmlspecialchars($emp['dono_user']); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        <?php echo date('d/m/Y', strtotime($emp['data_criacao'])); ?><br>
                                        <?php echo date('H:i', strtotime($emp['data_criacao'])); ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger" onclick="excluirEmpresaAdmin(<?php echo $emp['id']; ?>, '<?php echo addslashes($emp['nome']); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function excluirEmpresaAdmin(id, nome) {
    if (confirm(`AVISO DE ADMINISTRADOR: Tem certeza que deseja excluir a empresa "${nome}"? Esta ação é irreversível e apagará todos os dados financeiros, tarefas e conteúdos desta empresa.`)) {
        // Usa o handler existente no root para manter consistência
        fetch(`../ge_handlers.php?acao=excluir_empresa&id=${id}`)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Erro ao excluir: ' + d.message);
            }
        });
    }
}
</script>

<?php require_once 'footer_admin.php'; ?>
