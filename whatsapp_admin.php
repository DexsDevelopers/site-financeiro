<?php
// whatsapp_admin.php - Painel Administrativo WhatsApp (Versão Melhorada)
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

// Buscar estatísticas
$info = ['total'=>0,'com_tel'=>0,'com_e164'=>0];
try {
    $info['total'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $info['com_tel'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE (telefone IS NOT NULL AND telefone <> '')")->fetchColumn();
    $info['com_e164'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE (telefone_e164 IS NOT NULL AND telefone_e164 <> '')")->fetchColumn();
} catch (Throwable $e) {
    $info['erro'] = 'Falha ao contar usuários.';
}

// Verificar status do bot
$botStatus = ['online' => false, 'message' => 'Verificando...'];
$cfg = wpp_get_config();
try {
    $ch = curl_init($cfg['base'] . '/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-api-token: ' . $cfg['token']]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $statusData = json_decode($response, true);
        $botStatus['online'] = !empty($statusData['ready']);
        $botStatus['message'] = $botStatus['online'] ? 'Online e Conectado' : 'Offline ou Não Conectado';
    } else {
        $botStatus['message'] = 'Erro ao conectar';
    }
} catch (Exception $e) {
    $botStatus['message'] = 'Bot não disponível';
}

$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = trim($_POST['mensagem'] ?? '');
    $apenasComDDI = isset($_POST['usar_e164']);
    $limite = max(1, min(10000, (int)($_POST['limite'] ?? 1000)));
    $offset = max(0, (int)($_POST['offset'] ?? 0));
    $dryRun = isset($_POST['dryrun']);
    $filtroTipo = $_POST['filtro_tipo'] ?? 'todos';
    
    $enviados = 0; $falhas = 0; $logs = [];
    if ($mensagem === '') {
        $resultado = ['ok'=>false,'erro'=>'Informe a mensagem.'];
    } else {
        ignore_user_abort(true);
        set_time_limit(0);
        try {
            // Query base
            $sqlBase = "SELECT id, nome, telefone, telefone_e164 FROM usuarios WHERE 1=1";
            $params = [];
            
            // Filtros
            if ($filtroTipo === 'com_e164') {
                $sqlBase .= " AND (telefone_e164 IS NOT NULL AND telefone_e164 <> '')";
            } elseif ($filtroTipo === 'com_telefone') {
                $sqlBase .= " AND (telefone IS NOT NULL AND telefone <> '')";
            } elseif ($filtroTipo === 'sem_telefone') {
                $sqlBase .= " AND ((telefone IS NULL OR telefone = '') AND (telefone_e164 IS NULL OR telefone_e164 = ''))";
            }
            
            if ($apenasComDDI) {
                $sqlBase .= " AND (telefone_e164 IS NOT NULL AND telefone_e164 <> '')";
            } else {
                $sqlBase .= " AND ((telefone IS NOT NULL AND telefone <> '') OR (telefone_e164 IS NOT NULL AND telefone_e164 <> ''))";
            }
            
            $sqlBase .= " ORDER BY id ASC LIMIT :lim OFFSET :off";
            
            $stmt = $pdo->prepare($sqlBase);
            $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Deduplicação por número canônico no lote
            $seen = [];
            foreach ($destinatarios as $row) {
                $toRaw = $row['telefone_e164'] ?? $row['telefone'] ?? '';
                $to = wpp_normalize_number($toRaw);

                if (!$to) {
                    $falhas++;
                    $logs[] = ['id'=>$row['id'],'nome'=>$row['nome']??'','status'=>'ignorado_invalid_number','raw'=>$toRaw];
                    continue;
                }
                
                $canon = preg_replace('/\D+/', '', $to);
                if (isset($seen[$canon])) {
                    $logs[] = ['id'=>$row['id'],'nome'=>$row['nome']??'','status'=>'duplicate_in_batch','to'=>$to,'wa'=>"https://wa.me/{$canon}"];
                    continue;
                }
                $seen[$canon] = true;

                // Verificar se número está registrado
                $check = wpp_test_number($to);
                if (empty($check['ok'])) {
                    $falhas++;
                    $logs[] = ['id'=>$row['id'],'nome'=>$row['nome']??'','status'=>'not_registered','to'=>$to,'wa'=>"https://wa.me/{$canon}",'error'=>$check['error'] ?? 'unknown'];
                    continue;
                }

                if ($dryRun) { 
                    $enviados++; 
                    $logs[] = ['id'=>$row['id'],'nome'=>$row['nome']??'','to'=>$to,'wa'=>"https://wa.me/{$canon}",'status'=>'dry']; 
                    continue; 
                }

                // Envio real
                $resp = wpp_send_message($canon, $mensagem);
                if (!empty($resp['ok'])) { 
                    $enviados++; 
                    $logs[] = ['id'=>$row['id'],'nome'=>$row['nome']??'','to'=>$to,'wa'=>"https://wa.me/{$canon}",'ok'=>true]; 
                } else { 
                    $falhas++; 
                    $logs[] = ['id'=>$row['id'],'nome'=>$row['nome']??'','to'=>$to,'wa'=>"https://wa.me/{$canon}",'ok'=>false,'error'=>$resp['error'] ?? 'erro_desconhecido']; 
                }
                usleep(200000); // 200ms entre envios
            }
            $resultado = ['ok'=>true,'enviados'=>$enviados,'falhas'=>$falhas,'processados'=>count($destinatarios),'logs'=>$logs];
        } catch (Throwable $e) {
            $resultado = ['ok'=>false,'erro'=>'Falha ao enviar: '.$e->getMessage()];
        }
    }
}
?>
<style>
    .whatsapp-panel {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: calc(100vh - 200px);
        padding: 2rem 0;
    }
    .stats-card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
        height: 100%;
    }
    .stats-card:hover {
        transform: translateY(-5px);
    }
    .stats-card .icon {
        font-size: 2.5rem;
        color: #25D366;
        margin-bottom: 0.5rem;
    }
    .stats-card .number {
        font-size: 2rem;
        font-weight: 700;
        color: #333;
    }
    .stats-card .label {
        color: #666;
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }
    .bot-status-card {
        background: <?php echo $botStatus['online'] ? 'rgba(40, 167, 69, 0.1)' : 'rgba(220, 53, 69, 0.1)'; ?>;
        border: 2px solid <?php echo $botStatus['online'] ? '#28a745' : '#dc3545'; ?>;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
    }
    .bot-status-card .status-icon {
        font-size: 3rem;
        color: <?php echo $botStatus['online'] ? '#28a745' : '#dc3545'; ?>;
    }
    .form-section {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        margin-top: 2rem;
    }
    .result-card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        margin-top: 2rem;
    }
    .progress-bar-custom {
        height: 30px;
        border-radius: 15px;
        overflow: hidden;
    }
    .log-item {
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        border-radius: 8px;
        border-left: 4px solid;
        background: #f8f9fa;
    }
    .log-item.success {
        border-color: #28a745;
        background: rgba(40, 167, 69, 0.1);
    }
    .log-item.error {
        border-color: #dc3545;
        background: rgba(220, 53, 69, 0.1);
    }
    .log-item.warning {
        border-color: #ffc107;
        background: rgba(255, 193, 7, 0.1);
    }
    .template-btn {
        margin: 0.25rem;
    }
</style>

<div class="whatsapp-panel">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0 text-white">
                <i class="bi bi-whatsapp me-2"></i>Painel WhatsApp
            </h1>
            <a href="whatsapp_qr.php" class="btn btn-light">
                <i class="bi bi-qr-code me-2"></i>Ver QR Code
            </a>
        </div>

        <!-- Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="icon"><i class="bi bi-people"></i></div>
                    <div class="number"><?php echo number_format($info['total']); ?></div>
                    <div class="label">Total de Usuários</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="icon"><i class="bi bi-telephone"></i></div>
                    <div class="number"><?php echo number_format($info['com_tel']); ?></div>
                    <div class="label">Com Telefone</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <div class="icon"><i class="bi bi-telephone-fill"></i></div>
                    <div class="number"><?php echo number_format($info['com_e164']); ?></div>
                    <div class="label">Com E.164</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="bot-status-card">
                    <div class="status-icon">
                        <i class="bi <?php echo $botStatus['online'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>"></i>
                    </div>
                    <div class="mt-2">
                        <strong><?php echo htmlspecialchars($botStatus['message']); ?></strong>
                        <br><small class="text-muted"><?php echo htmlspecialchars($cfg['base']); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário de Envio -->
        <div class="form-section">
            <h3 class="mb-4"><i class="bi bi-send me-2"></i>Enviar Mensagem em Massa</h3>
            
            <form method="POST" id="formEnvio" class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-bold">Mensagem</label>
                    <textarea name="mensagem" id="mensagem" class="form-control" rows="5" 
                              placeholder="Digite sua mensagem aqui..." required><?php echo htmlspecialchars($_POST['mensagem'] ?? ''); ?></textarea>
                    <small class="text-muted">Caracteres: <span id="charCount">0</span></small>
                    
                    <!-- Templates Rápidos -->
                    <div class="mt-2">
                        <small class="text-muted d-block mb-2">Templates:</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary template-btn" onclick="inserirTemplate('Olá! Esta é uma mensagem de teste do sistema.')">Template Teste</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary template-btn" onclick="inserirTemplate('Olá {{nome}}! Temos uma novidade importante para você.')">Template Personalizado</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary template-btn" onclick="inserirTemplate('Lembrete: Sua conta está ativa e funcionando perfeitamente!')">Template Lembrete</button>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Filtro de Usuários</label>
                    <select name="filtro_tipo" class="form-select">
                        <option value="todos" <?php echo ($_POST['filtro_tipo'] ?? 'todos') === 'todos' ? 'selected' : ''; ?>>Todos os usuários</option>
                        <option value="com_e164" <?php echo ($_POST['filtro_tipo'] ?? '') === 'com_e164' ? 'selected' : ''; ?>>Apenas com E.164</option>
                        <option value="com_telefone" <?php echo ($_POST['filtro_tipo'] ?? '') === 'com_telefone' ? 'selected' : ''; ?>>Apenas com Telefone</option>
                        <option value="sem_telefone" <?php echo ($_POST['filtro_tipo'] ?? '') === 'sem_telefone' ? 'selected' : ''; ?>>Sem Telefone</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Limite de Envios</label>
                    <input type="number" class="form-control" name="limite" value="<?php echo (int)($_POST['limite'] ?? 1000); ?>" min="1" max="10000">
                    <small class="text-muted">Máximo: 10.000</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Offset (Pular)</label>
                    <input type="number" class="form-control" name="offset" value="<?php echo (int)($_POST['offset'] ?? 0); ?>" min="0">
                    <small class="text-muted">Para processar em lotes</small>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="usar_e164" name="usar_e164" <?php echo !empty($_POST['usar_e164'])?'checked':''; ?>>
                        <label class="form-check-label" for="usar_e164">
                            <strong>Usar apenas campo E.164</strong> (mais confiável, ignora telefones sem DDI)
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dryrun" name="dryrun" <?php echo !empty($_POST['dryrun'])?'checked':''; ?>>
                        <label class="form-check-label" for="dryrun">
                            <strong>Dry-run (Simulação)</strong> - Não envia mensagens, apenas simula o envio
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> O envio em massa pode levar alguns minutos. Não feche esta página durante o processo.
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-success btn-lg" id="btnEnviar">
                        <i class="bi bi-send me-2"></i>Enviar Mensagens
                    </button>
                    <a href="whatsapp_qr.php" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-qr-code me-2"></i>QR Code
                    </a>
                    <a href="whatsapp_admin.php" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-clockwise me-2"></i>Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <?php if ($resultado !== null): ?>
        <div class="result-card">
            <h3 class="mb-4"><i class="bi bi-clipboard-data me-2"></i>Resultado do Envio</h3>
            
            <?php if (!empty($resultado['ok'])): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="alert alert-info text-center">
                            <div class="h4 mb-0"><?php echo number_format($resultado['processados']); ?></div>
                            <small>Processados</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success text-center">
                            <div class="h4 mb-0"><?php echo number_format($resultado['enviados']); ?></div>
                            <small>Enviados com Sucesso</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-danger text-center">
                            <div class="h4 mb-0"><?php echo number_format($resultado['falhas']); ?></div>
                            <small>Falhas</small>
                        </div>
                    </div>
                </div>

                <?php if ($resultado['processados'] > 0): ?>
                <div class="progress progress-bar-custom mb-3">
                    <div class="progress-bar bg-success" role="progressbar" 
                         style="width: <?php echo ($resultado['enviados'] / $resultado['processados']) * 100; ?>%">
                        <?php echo number_format(($resultado['enviados'] / $resultado['processados']) * 100, 1); ?>%
                    </div>
                </div>
                <?php endif; ?>

                <details class="mt-3">
                    <summary class="btn btn-outline-secondary">
                        <i class="bi bi-list-ul me-2"></i>Ver Logs Detalhados (<?php echo count($resultado['logs']); ?> registros)
                    </summary>
                    <div class="mt-3" style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($resultado['logs'] as $log): ?>
                            <div class="log-item <?php 
                                echo isset($log['ok']) && $log['ok'] ? 'success' : 
                                    (isset($log['status']) && $log['status'] === 'dry' ? 'warning' : 'error'); 
                            ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>ID:</strong> <?php echo htmlspecialchars($log['id'] ?? 'N/A'); ?>
                                        <?php if (!empty($log['nome'])): ?>
                                            | <strong>Nome:</strong> <?php echo htmlspecialchars($log['nome']); ?>
                                        <?php endif; ?>
                                        <br>
                                        <?php if (!empty($log['to'])): ?>
                                            <strong>Número:</strong> <?php echo htmlspecialchars($log['to']); ?>
                                            <?php if (!empty($log['wa'])): ?>
                                                <a href="<?php echo htmlspecialchars($log['wa']); ?>" target="_blank" class="btn btn-sm btn-link">
                                                    <i class="bi bi-whatsapp"></i> Abrir WhatsApp
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <br>
                                        <strong>Status:</strong> 
                                        <?php 
                                        if (isset($log['ok']) && $log['ok']) {
                                            echo '<span class="badge bg-success">Enviado</span>';
                                        } elseif (isset($log['status'])) {
                                            echo '<span class="badge bg-warning">' . htmlspecialchars($log['status']) . '</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">Erro: ' . htmlspecialchars($log['error'] ?? 'Desconhecido') . '</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="mt-3">
                    <summary class="btn btn-outline-secondary">
                        <i class="bi bi-code me-2"></i>Ver JSON Completo
                    </summary>
                    <pre class="mt-3 p-3 bg-dark text-white rounded" style="max-height: 400px; overflow-y: auto;"><?php echo htmlspecialchars(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </details>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Erro:</strong> <?php echo htmlspecialchars($resultado['erro'] ?? 'Erro desconhecido'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Contador de caracteres
document.getElementById('mensagem').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});
document.getElementById('charCount').textContent = document.getElementById('mensagem').value.length;

// Inserir template
function inserirTemplate(template) {
    document.getElementById('mensagem').value = template;
    document.getElementById('charCount').textContent = template.length;
    document.getElementById('mensagem').focus();
}

// Confirmação antes de enviar
document.getElementById('formEnvio').addEventListener('submit', function(e) {
    const dryRun = document.getElementById('dryrun').checked;
    const limite = parseInt(document.getElementById('formEnvio').querySelector('input[name="limite"]').value);
    const mensagem = document.getElementById('mensagem').value.trim();
    
    if (!mensagem) {
        e.preventDefault();
        alert('Por favor, digite uma mensagem.');
        return;
    }
    
    if (!dryRun && !confirm(`Você está prestes a enviar ${limite} mensagens. Deseja continuar?`)) {
        e.preventDefault();
        return;
    }
    
    const btnEnviar = document.getElementById('btnEnviar');
    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
});
</script>

<?php require_once 'templates/footer.php'; ?>
