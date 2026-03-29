<?php
require_once 'templates/header.php';
require_once 'includes/db_connect.php';

// Garantir tabelas
if ($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS telegram_usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            chat_id BIGINT NOT NULL UNIQUE,
            username VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS telegram_vincular_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code VARCHAR(32) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            INDEX idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

$cfg      = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$botToken = $cfg['TELEGRAM_BOT_TOKEN'] ?? '';
$baseUrl  = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

// Checar se já vinculado
$stmt = $pdo->prepare("SELECT username, created_at FROM telegram_usuarios WHERE user_id = ?");
$stmt->execute([$userId]);
$vinculado = $stmt->fetch();

// Ação: gerar código
$novoCodigo = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'gerar_codigo') {
        $codigo = strtoupper(bin2hex(random_bytes(8))); // 16 chars
        $pdo->prepare("DELETE FROM telegram_vincular_codes WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("INSERT INTO telegram_vincular_codes (user_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")
            ->execute([$userId, $codigo]);
        $novoCodigo = $codigo;
    } elseif ($_POST['acao'] === 'desvincular') {
        $pdo->prepare("DELETE FROM telegram_usuarios WHERE user_id = ?")->execute([$userId]);
        header('Location: vincular_telegram.php?msg=desvinculado');
        exit;
    }
}

// Buscar código pendente
$stmtCod = $pdo->prepare("SELECT code, expires_at FROM telegram_vincular_codes WHERE user_id = ? AND expires_at > NOW()");
$stmtCod->execute([$userId]);
$codigoPendente = $stmtCod->fetch();
if ($novoCodigo) {
    $codigoPendente = ['code' => $novoCodigo, 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes'))];
}

// Nome do bot (extrair do token)
$botId = explode(':', $botToken)[0] ?? '';
?>

<style>
.tg-container { max-width: 680px; margin: 0 auto; padding: 2rem 1rem 4rem; }
.tg-card {
    background: rgba(22,22,24,.85);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 22px;
    padding: 2rem 2.2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 30px rgba(0,0,0,.3);
}
.tg-header { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; }
.tg-icon {
    width:52px; height:52px; border-radius:14px;
    background: linear-gradient(135deg, #229ED9, #1a7ab5);
    display:flex; align-items:center; justify-content:center;
    font-size:1.7rem; flex-shrink:0;
}
.tg-title { font-size:1.25rem; font-weight:700; color:#fff; margin:0; }
.tg-subtitle { font-size:.85rem; color:#8e8e93; margin:2px 0 0; }
.tg-status-ok {
    display:flex; align-items:center; gap:.75rem;
    background:rgba(48,209,88,.08); border:1px solid rgba(48,209,88,.25);
    border-radius:14px; padding:1rem 1.25rem; margin-bottom:1.5rem;
    color:#30d158; font-weight:600;
}
.tg-status-ok i { font-size:1.4rem; }
.code-box {
    background:#000; border:1px solid rgba(255,255,255,.12);
    border-radius:14px; padding:1.25rem 1.5rem;
    font-family:monospace; font-size:1.5rem; letter-spacing:4px;
    color:#ffd60a; text-align:center; margin:1rem 0;
    position:relative;
}
.code-copy {
    position:absolute; right:.75rem; top:50%; transform:translateY(-50%);
    background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);
    color:#fff; padding:6px 14px; border-radius:8px; cursor:pointer;
    font-size:.8rem; font-weight:600; font-family:'Lexend Deca',sans-serif;
    transition:all .2s;
}
.code-copy:hover { background:rgba(255,255,255,.15); }
.step { display:flex; gap:.85rem; align-items:flex-start; margin-bottom:.9rem; }
.step-num {
    width:26px; height:26px; border-radius:50%; flex-shrink:0;
    background:rgba(229,9,20,.15); border:1px solid rgba(229,9,20,.3);
    color:#e50914; font-size:.8rem; font-weight:700;
    display:flex; align-items:center; justify-content:center;
}
.step-text { color:#ccc; font-size:.9rem; line-height:1.5; }
.step-text b { color:#fff; }
.btn-gerar {
    background:linear-gradient(135deg,#229ED9,#1a7ab5);
    border:none; color:#fff; padding:12px 28px; border-radius:13px;
    font-weight:600; font-size:.9rem; cursor:pointer; width:100%;
    transition:all .25s; display:flex; align-items:center; justify-content:center; gap:.5rem;
}
.btn-gerar:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(34,158,217,.35); }
.btn-desvincular {
    background:rgba(255,69,58,.08); border:1px solid rgba(255,69,58,.25);
    color:#ff453a; padding:10px 22px; border-radius:11px; font-weight:600;
    font-size:.85rem; cursor:pointer; transition:all .2s;
}
.btn-desvincular:hover { background:rgba(255,69,58,.15); }
.expires-badge {
    display:inline-flex; align-items:center; gap:.4rem;
    background:rgba(255,214,10,.08); border:1px solid rgba(255,214,10,.2);
    color:#ffd60a; font-size:.78rem; padding:4px 12px; border-radius:20px;
    font-weight:600; margin-top:.5rem;
}
.tg-examples {
    background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06);
    border-radius:14px; padding:1.2rem 1.5rem; margin-top:1rem;
}
.tg-examples h6 { color:#8e8e93; font-size:.75rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:.75rem; }
.example-item { font-size:.88rem; color:#ccc; padding:4px 0; display:flex; align-items:center; gap:.6rem; }
.example-item::before { content:"›"; color:#e50914; font-weight:700; }
</style>

<div class="tg-container">
    <div class="tg-card">
        <div class="tg-header">
            <div class="tg-icon">✈️</div>
            <div>
                <h4 class="tg-title">Integração Telegram</h4>
                <p class="tg-subtitle">Controle seu painel financeiro direto pelo Telegram</p>
            </div>
        </div>

        <?php if ($vinculado): ?>
            <!-- JÁ VINCULADO -->
            <div class="tg-status-ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <div>Conta vinculada com sucesso</div>
                    <?php if ($vinculado['username']): ?>
                        <small style="opacity:.7">@<?= htmlspecialchars($vinculado['username']) ?> · desde <?= date('d/m/Y', strtotime($vinculado['created_at'])) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tg-examples">
                <h6>Exemplos de comandos</h6>
                <div class="example-item">gastei 120 no mercado</div>
                <div class="example-item">recebi 3000 de salário</div>
                <div class="example-item">criar tarefa pagar boleto amanhã</div>
                <div class="example-item">meu saldo</div>
                <div class="example-item">quanto gastei esse mês</div>
            </div>

            <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
                <form method="POST">
                    <input type="hidden" name="acao" value="desvincular">
                    <button type="submit" class="btn-desvincular" onclick="return confirm('Desvincular conta do Telegram?')">
                        <i class="bi bi-unlink me-1"></i> Desvincular
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- NÃO VINCULADO -->
            <div style="margin-bottom:1.5rem;">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-text">
                        Clique em <b>Gerar Código</b> abaixo para criar um código de vinculação válido por 30 minutos
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-text">
                        Abra o Telegram e inicie uma conversa com o bot:<br>
                        <b>t.me/OrionPainelBot</b> <small style="color:#8e8e93">(ou pesquise pelo nome)</small>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        Envie o comando: <b>/start SEU_CODIGO</b>
                    </div>
                </div>
            </div>

            <?php if ($codigoPendente): ?>
                <div style="text-align:center;">
                    <p style="color:#8e8e93; font-size:.85rem; margin-bottom:.25rem;">Seu código de vinculação:</p>
                    <div class="code-box" id="codeBox">
                        <?= htmlspecialchars($codigoPendente['code']) ?>
                        <button class="code-copy" onclick="copiarCodigo()">Copiar</button>
                    </div>
                    <div class="expires-badge">
                        <i class="bi bi-clock"></i>
                        Expira em <?= date('H:i', strtotime($codigoPendente['expires_at'])) ?>
                    </div>
                    <p style="color:#8e8e93; font-size:.82rem; margin-top:.75rem;">
                        Envie no Telegram: <code style="color:#ffd60a;">/start <?= htmlspecialchars($codigoPendente['code']) ?></code>
                    </p>
                </div>
                <form method="POST" style="margin-top:1rem;">
                    <input type="hidden" name="acao" value="gerar_codigo">
                    <button type="submit" class="btn-gerar" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);">
                        <i class="bi bi-arrow-clockwise"></i> Gerar novo código
                    </button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="acao" value="gerar_codigo">
                    <button type="submit" class="btn-gerar">
                        <i class="bi bi-telegram"></i> Gerar Código de Vinculação
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Card informativo -->
    <div class="tg-card" style="padding:1.5rem 2rem;">
        <h6 style="color:#8e8e93; font-size:.75rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:1rem;">O que você pode fazer pelo Telegram</h6>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.6rem;">
            <div style="display:flex;align-items:center;gap:.6rem;color:#ccc;font-size:.85rem;"><span style="color:#e50914">💸</span> Lançar despesas</div>
            <div style="display:flex;align-items:center;gap:.6rem;color:#ccc;font-size:.85rem;"><span style="color:#30d158">💰</span> Registrar receitas</div>
            <div style="display:flex;align-items:center;gap:.6rem;color:#ccc;font-size:.85rem;"><span style="color:#4da6ff">✅</span> Criar tarefas</div>
            <div style="display:flex;align-items:center;gap:.6rem;color:#ccc;font-size:.85rem;"><span style="color:#ffd60a">📊</span> Ver saldo</div>
            <div style="display:flex;align-items:center;gap:.6rem;color:#ccc;font-size:.85rem;"><span style="color:#bf5af2">🎯</span> Criar metas</div>
            <div style="display:flex;align-items:center;gap:.6rem;color:#ccc;font-size:.85rem;"><span style="color:#ff9f0a">📈</span> Relatórios</div>
        </div>
    </div>
</div>

<script>
function copiarCodigo() {
    const code = document.getElementById('codeBox').childNodes[0].textContent.trim();
    navigator.clipboard.writeText('/start ' + code).then(() => {
        const btn = document.querySelector('.code-copy');
        btn.textContent = '✓ Copiado!';
        setTimeout(() => btn.textContent = 'Copiar', 2000);
    });
}
</script>

<?php require_once 'templates/footer.php'; ?>
