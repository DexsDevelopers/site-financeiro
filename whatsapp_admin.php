<?php
// whatsapp_admin.php - Enviar avisos em massa via WhatsApp
require_once 'templates/header.php';
require_once 'includes/whatsapp_client.php';
require_once 'includes/db_connect.php';

// Restringe a página a administradores
try {
    $uid = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : (int)($_SESSION['user_id'] ?? 0);
    $stmtAdm = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
    $stmtAdm->execute([$uid]);
    $tipoUser = $stmtAdm->fetchColumn();
    if ($tipoUser !== 'admin') {
        http_response_code(403);
        echo '<div class="alert alert-danger m-3">Acesso negado. Esta página é restrita a administradores.</div>';
        require_once 'templates/footer.php';
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger m-3">Erro ao verificar permissões.</div>';
    require_once 'templates/footer.php';
    exit;
}

$info = ['total'=>0,'com_tel'=>0];
try {
    $info['total'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $info['com_tel'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE (telefone_e164 IS NOT NULL AND telefone_e164 <> '') OR (telefone IS NOT NULL AND telefone <> '')")->fetchColumn();
} catch (Throwable $e) {
    $info['erro'] = 'Falha ao contar usuários.';
}

$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = trim($_POST['mensagem'] ?? '');
    $apenasComDDI = isset($_POST['usar_e164']);
    $limite = max(1, min(10000, (int)($_POST['limite'] ?? 1000)));
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $dryRun = isset($_POST['dryrun']);
    $enviados = 0; $falhas = 0; $logs = [];
    if ($mensagem === '') {
        $resultado = ['ok'=>false,'erro'=>'Informe a mensagem.'];
    } else {
        ignore_user_abort(true);
        set_time_limit(0);
        try {
            if ($apenasComDDI) {
                $stmt = $pdo->prepare("SELECT id, telefone_e164 FROM usuarios WHERE telefone_e164 IS NOT NULL AND telefone_e164 <> '' ORDER BY id ASC LIMIT :lim OFFSET :off");
            } else {
                $stmt = $pdo->prepare("SELECT id, telefone, telefone_e164 FROM usuarios WHERE (telefone IS NOT NULL AND telefone <> '') OR (telefone_e164 IS NOT NULL AND telefone_e164 <> '') ORDER BY id ASC LIMIT :lim OFFSET :off");
            }
            $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($destinatarios as $row) {
                // Prioriza E.164; se não existir, tenta normalizar BR
                $toRaw = $row['telefone_e164'] ?? $row['telefone'] ?? '';
                $to = wpp_normalize_number($toRaw);

                if (!$to) {
                    $falhas++;
                    $logs[] = ['id'=>$row['id'],'status'=>'ignorado_invalid_number','raw'=>$toRaw];
                    continue;
                }

                // Opcional: testar se número está registrado
                $check = wpp_test_number($to);
                if (empty($check['ok'])) {
                    $falhas++;
                    $logs[] = ['id'=>$row['id'],'status'=>'not_registered','to'=>$to,'error'=>$check['error'] ?? 'unknown'];
                    continue;
                }

                if ($dryRun) { $enviados++; $logs[] = ['id'=>$row['id'],'to'=>$to,'status'=>'dry']; continue; }

                // Envio real
                $resp = wpp_send_message($to, $mensagem);
                if (!empty($resp['ok'])) { $enviados++; $logs[] = ['id'=>$row['id'],'to'=>$to,'ok'=>true]; }
                else { $falhas++; $logs[] = ['id'=>$row['id'],'to'=>$to,'ok'=>false,'error'=>$resp['error'] ?? 'erro_desconhecido']; }
                usleep(200000); // 200ms entre envios
            }
            $resultado = ['ok'=>true,'enviados'=>$enviados,'falhas'=>$falhas,'processados'=>count($destinatarios),'logs'=>$logs];
        } catch (Throwable $e) {
            $resultado = ['ok'=>false,'erro'=>'Falha ao enviar: '.$e->getMessage()];
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-whatsapp me-2"></i>Envio de Avisos (WhatsApp)</h1>
</div>

<main class="container-fluid p-0">
    <div class="card card-glass">
        <div class="card-body p-4">
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="alert alert-secondary p-2 mb-2">Usuários: <strong><?php echo (int)$info['total']; ?></strong></div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-secondary p-2 mb-2">Com telefone: <strong><?php echo (int)$info['com_tel']; ?></strong></div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info p-2 mb-2">Status Bot: <code><?php $cfg = wpp_get_config(); echo htmlspecialchars($cfg['base']); ?></code></div>
                </div>
            </div>
            <form method="POST" class="row g-3">
                <div class="col-12">
                    <label class="form-label">Mensagem</label>
                    <textarea name="mensagem" class="form-control" rows="3" placeholder="Texto do aviso..." required><?php echo htmlspecialchars($_POST['mensagem'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Limite</label>
                    <input type="number" class="form-control" name="limite" value="<?php echo (int)($_POST['limite'] ?? 1000); ?>" min="1" max="10000">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Offset</label>
                    <input type="number" class="form-control" name="offset" value="<?php echo (int)($_POST['offset'] ?? 0); ?>" min="0">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="usar_e164" name="usar_e164" <?php echo !empty($_POST['usar_e164'])?'checked':''; ?>>
                        <label class="form-check-label" for="usar_e164">Usar apenas campo E.164 (mais confiável)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="dryrun" name="dryrun" <?php echo !empty($_POST['dryrun'])?'checked':''; ?>>
                        <label class="form-check-label" for="dryrun">Dry-run (não envia, só simula)</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-custom-red"><i class="bi bi-send me-1"></i>Enviar</button>
                    <a href="whatsapp_admin.php" class="btn btn-secondary">Limpar</a>
                </div>
            </form>

            <?php if ($resultado !== null): ?>
                <div class="mt-4">
                    <?php if (!empty($resultado['ok'])): ?>
                        <div class="alert alert-success">Processados: <?php echo (int)$resultado['processados']; ?> — Enviados: <?php echo (int)$resultado['enviados']; ?> — Falhas: <?php echo (int)$resultado['falhas']; ?></div>
                    <?php else: ?>
                        <div class="alert alert-danger">Erro: <?php echo htmlspecialchars($resultado['erro'] ?? ''); ?></div>
                    <?php endif; ?>
                    <details class="mt-2">
                        <summary>Ver logs</summary>
                        <pre class="mt-2 p-2 bg-dark text-white rounded" style="white-space: pre-wrap;"><?php echo htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                    </details>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'templates/footer.php'; ?>


