<?php
// verificar_estrutura.php
// Verifica se as tabelas e colunas essenciais do sistema existem
// e orienta como corrigir caso algo esteja faltando.

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/html; charset=utf-8');

$result = [
    'ok' => [],
    'warn' => [],
    'fail' => [],
    'details' => [],
];

function addItem(&$result, $status, $msg) {
    $result[$status][] = $msg;
}

try {
    require_once __DIR__ . '/includes/db_connect.php';
} catch (Throwable $e) {
    echo "<h1>❌ Falha ao conectar no banco</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t");
    $stmt->execute([':t' => $table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
    $stmt->execute([':t' => $table, ':c' => $column]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function indexExists(PDO $pdo, string $table, string $indexName): bool {
    $stmt = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = :k");
    $stmt->execute([':k' => $indexName]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

// ========== CHECKLIST ==========
$checks = [];

// Tabela usuarios (base)
$checks[] = function(PDO $pdo, array &$res) {
    if (tableExists($pdo, 'usuarios')) {
        addItem($res, 'ok', "Tabela 'usuarios' existe.");
    } else {
        addItem($res, 'fail', "Tabela 'usuarios' não encontrada.");
    }
};

// Tabela categorias (base)
$checks[] = function(PDO $pdo, array &$res) {
    if (tableExists($pdo, 'categorias')) {
        addItem($res, 'ok', "Tabela 'categorias' existe.");
    } else {
        addItem($res, 'fail', "Tabela 'categorias' não encontrada.");
    }
};

// Tabela transacoes + coluna id_conta
$checks[] = function(PDO $pdo, array &$res) {
    if (!tableExists($pdo, 'transacoes')) {
        addItem($res, 'fail', "Tabela 'transacoes' não encontrada.");
        return;
    }
    addItem($res, 'ok', "Tabela 'transacoes' existe.");
    if (columnExists($pdo, 'transacoes', 'id_conta')) {
        addItem($res, 'ok', "Coluna 'transacoes.id_conta' existe.");
        if (indexExists($pdo, 'transacoes', 'idx_id_conta')) {
            addItem($res, 'ok', "Índice 'idx_id_conta' em 'transacoes' existe.");
        } else {
            addItem($res, 'warn', "Índice 'idx_id_conta' em 'transacoes' não encontrado (recomendado).");
        }
    } else {
        addItem($res, 'fail', "Coluna 'transacoes.id_conta' não encontrada.");
    }
};

// Tabela contas e colunas
$checks[] = function(PDO $pdo, array &$res) {
    if (!tableExists($pdo, 'contas')) {
        addItem($res, 'fail', "Tabela 'contas' não encontrada.");
        return;
    }
    addItem($res, 'ok', "Tabela 'contas' existe.");
    $mustHave = ['id','id_usuario','nome','tipo','instituicao','saldo_inicial','cor','criado_em'];
    foreach ($mustHave as $col) {
        if (columnExists($pdo, 'contas', $col)) {
            addItem($res, 'ok', "Coluna 'contas.$col' existe.");
        } else {
            addItem($res, 'fail', "Coluna 'contas.$col' não encontrada.");
        }
    }
    // Campo opcional legado
    if (columnExists($pdo, 'contas', 'codigo_conta')) {
        addItem($res, 'ok', "Coluna opcional 'contas.codigo_conta' encontrada.");
    }
};

// Executa os checks
foreach ($checks as $fn) {
    try { $fn($pdo, $result); } catch (Throwable $e) { addItem($result, 'fail', "Erro ao checar: " . $e->getMessage()); }
}

// === Render HTML mínimo ===
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificação de Estrutura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0f0f10; color: #f5f5f1; font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial, sans-serif; }
        .card { background: rgba(30,30,30,0.6); border: 1px solid rgba(255,255,255,0.1); }
        .badge-ok { background: #198754; }
        .badge-warn { background: #ffc107; color: #111; }
        .badge-fail { background: #dc3545; }
        code { color: #ffc107; }
        a.btn-link { color: #0d6efd; text-decoration: none; }
        a.btn-link:hover { text-decoration: underline; }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width: 900px;">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h3 mb-0">Verificação de Estrutura de Banco</h1>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Voltar</a>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Resumo</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge badge-ok">OK: <?php echo count($result['ok']); ?></span>
                    <span class="badge badge-warn">Avisos: <?php echo count($result['warn']); ?></span>
                    <span class="badge badge-fail">Erros: <?php echo count($result['fail']); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($result['fail'])): ?>
            <div class="alert alert-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        Existem problemas na estrutura do banco. Você pode tentar corrigir rodando a migração:
                        <code>criar_tabelas_contas.php</code>
                    </div>
                    <a class="btn btn-sm btn-danger" href="criar_tabelas_contas.php">Executar Migração</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">OK</h6>
                        <ul class="mb-0">
                            <?php foreach ($result['ok'] as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                            <?php if (empty($result['ok'])): ?>
                                <li>Nenhum item OK.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">Avisos</h6>
                        <ul class="mb-0">
                            <?php foreach ($result['warn'] as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                            <?php if (empty($result['warn'])): ?>
                                <li>Nenhum aviso.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">Erros</h6>
                        <ul class="mb-0">
                            <?php foreach ($result['fail'] as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                            <?php if (empty($result['fail'])): ?>
                                <li>Nenhum erro.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <a class="btn btn-outline-light btn-sm" href="verificar_estrutura.php">Recarregar</a>
            <a class="btn btn-link btn-sm" href="diagnosticar_deploy.php">Diagnóstico de Deploy</a>
        </div>
    </div>
</body>
</html>


