<?php
// contas.php - Gestão simples de contas/carteiras
date_default_timezone_set('America/Sao_Paulo');
require_once 'templates/header.php';
// $pdo, $userId, $paginaAtual já definidos pelo header

$msg = '';
$isOk = false;

// Helpers
function sanitizeColor($color) {
    if (!$color) return null;
    $color = trim($color);
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) return null;
    return strtoupper($color);
}

// Processamento de ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $nome = trim($_POST['nome'] ?? '');
            $tipo = trim($_POST['tipo'] ?? 'banco');
            $instituicao = trim($_POST['instituicao'] ?? '');
            $saldo_inicial = isset($_POST['saldo_inicial']) ? (float)$_POST['saldo_inicial'] : 0;
            $cor = sanitizeColor($_POST['cor'] ?? null);

            if ($nome === '') throw new Exception('Informe o nome da conta.');
            if (!in_array($tipo, ['banco','cartao','dinheiro','outro'], true)) $tipo = 'banco';

            // Detectar coluna codigo_conta (algumas instalações exigem valor único)
            $hasCodigoConta = (bool)$pdo->query("SHOW COLUMNS FROM contas LIKE 'codigo_conta'")->fetch(PDO::FETCH_ASSOC);
            if ($hasCodigoConta) {
                try { $pdo->exec("ALTER TABLE contas MODIFY COLUMN codigo_conta VARCHAR(64) NULL"); } catch (Throwable $e) {}
                $codigo = bin2hex(random_bytes(8));
                $stmt = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, instituicao, saldo_inicial, cor, codigo_conta) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $nome, $tipo, $instituicao ?: null, $saldo_inicial, $cor, $codigo]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, instituicao, saldo_inicial, cor) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $nome, $tipo, $instituicao ?: null, $saldo_inicial, $cor]);
            }
            $isOk = true; $msg = 'Conta criada com sucesso.';
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $tipo = trim($_POST['tipo'] ?? 'banco');
            $instituicao = trim($_POST['instituicao'] ?? '');
            $saldo_inicial = isset($_POST['saldo_inicial']) ? (float)$_POST['saldo_inicial'] : 0;
            $cor = sanitizeColor($_POST['cor'] ?? null);

            if ($id <= 0) throw new Exception('Conta inválida.');
            if ($nome === '') throw new Exception('Informe o nome da conta.');
            if (!in_array($tipo, ['banco','cartao','dinheiro','outro'], true)) $tipo = 'banco';

            $stmt = $pdo->prepare("SELECT id FROM contas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetchColumn()) throw new Exception('Conta não encontrada.');

            $stmt = $pdo->prepare("UPDATE contas SET nome = ?, tipo = ?, instituicao = ?, saldo_inicial = ?, cor = ? WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$nome, $tipo, $instituicao ?: null, $saldo_inicial, $cor, $id, $userId]);
            $isOk = true; $msg = 'Conta atualizada com sucesso.';
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Conta inválida.');

            // Verifica vínculo com transações
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transacoes WHERE id_usuario = ? AND id_conta = ?");
            $stmt->execute([$userId, $id]);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) throw new Exception('Há transações vinculadas a esta conta. Mova-as para outra conta antes de excluir.');

            $stmt = $pdo->prepare("DELETE FROM contas WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$id, $userId]);
            $isOk = true; $msg = 'Conta excluída com sucesso.';
        }
    } catch (Throwable $e) {
        $isOk = false;
        $msg = $e->getMessage();
    }
}

// Se estiver em modo edição (?edit=ID)
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$contaEdit = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM contas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$editId, $userId]);
    $contaEdit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Listagem (detecção direta tentando selecionar)
$contas = [];
$tabelaExiste = true;
try { 
    // Tenta acesso direto à tabela; se falhar, consideramos inexistente
    $pdo->query("SELECT 1 FROM contas LIMIT 1");
    $stmt = $pdo->prepare("SELECT id, nome, tipo, instituicao, saldo_inicial, cor, criado_em FROM contas WHERE id_usuario = ? ORDER BY nome");
    $stmt->execute([$userId]);
    $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tabelaExiste = false;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0">Contas</h1>
    <a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-grid-1x2 me-1"></i> Dashboard</a>
</div>

<main class="container-fluid p-0">
    <?php if ($msg): ?>
        <div class="alert <?php echo $isOk ? 'alert-success' : 'alert-danger'; ?> p-2" role="alert">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card card-glass">
                <div class="card-body p-4">
                    <h4 class="card-title mb-3"><?php echo $contaEdit ? 'Editar Conta' : 'Nova Conta'; ?></h4>
                    <form method="POST">
                        <?php if ($contaEdit): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$contaEdit['id']; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="action" value="<?php echo $contaEdit ? 'update' : 'create'; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" required value="<?php echo htmlspecialchars($contaEdit['nome'] ?? ''); ?>" placeholder="Ex: Carteira, Banco XP, Cartão Y">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="tipo">
                                    <?php
                                        $tipos = ['banco' => 'Banco', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'outro' => 'Outro'];
                                        $selTipo = $contaEdit['tipo'] ?? 'banco';
                                        foreach ($tipos as $k => $label) {
                                            $sel = ($selTipo === $k) ? 'selected' : '';
                                            echo "<option value=\"$k\" $sel>$label</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Instituição (opcional)</label>
                                <input type="text" class="form-control" name="instituicao" value="<?php echo htmlspecialchars($contaEdit['instituicao'] ?? ''); ?>" placeholder="Ex: Nubank, Itaú, Carteira">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Saldo Inicial (R$)</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="saldo_inicial" value="<?php echo htmlspecialchars($contaEdit['saldo_inicial'] ?? '0'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cor (opcional)</label>
                                <input type="color" class="form-control form-control-color" name="cor" value="<?php echo htmlspecialchars($contaEdit['cor'] ?? '#e50914'); ?>">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-custom-red"><?php echo $contaEdit ? 'Salvar Alterações' : 'Criar Conta'; ?></button>
                            <?php if ($contaEdit): ?>
                                <a href="contas.php" class="btn btn-secondary">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-glass">
                <div class="card-body p-4">
                    <h4 class="card-title mb-3">Minhas Contas</h4>
                    <?php if (!$tabelaExiste): ?>
                        <div class="alert alert-warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Estrutura não encontrada (tabela <code>contas</code>). Crie a estrutura para continuar.</span>
                                <a class="btn btn-sm btn-custom-red" href="criar_tabelas_contas.php">Criar Estrutura</a>
                            </div>
                        </div>
                        <p class="text-muted mb-0">Após criar, recarregue esta página.</p>
                    <?php elseif (empty($contas)): ?>
                        <p class="text-muted">Nenhuma conta cadastrada ainda.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle" style="color: var(--text-primary);">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Tipo</th>
                                        <th>Instituição</th>
                                        <th>Saldo Inicial</th>
                                        <th>Cor</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contas as $conta): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($conta['nome']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($conta['tipo'])); ?></span></td>
                                            <td><?php echo htmlspecialchars($conta['instituicao'] ?: '—'); ?></td>
                                            <td>R$ <?php echo number_format((float)$conta['saldo_inicial'], 2, ',', '.'); ?></td>
                                            <td>
                                                <?php if (!empty($conta['cor'])): ?>
                                                    <span class="d-inline-block" style="width:18px;height:18px;border-radius:4px;background: <?php echo htmlspecialchars($conta['cor']); ?>; border: 1px solid var(--border-color);"></span>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="contas.php?edit=<?php echo (int)$conta['id']; ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil-square"></i></a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir esta conta? Esta ação não pode ser desfeita.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$conta['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once 'templates/footer.php';
?>


