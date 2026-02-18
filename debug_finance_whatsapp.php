<?php
// debug_finance_whatsapp.php - Página de diagnóstico do sistema WhatsApp Financeiro
require_once 'templates/header.php';
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

// Carregar configuração
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$errors = [];
$success = [];
$warnings = [];

// Verificar configuração
if (!$config) {
    $errors[] = 'Arquivo config.json não encontrado ou inválido';
} else {
    $success[] = 'Configuração carregada com sucesso';
    
    // Verificar campos obrigatórios
    $required = ['WHATSAPP_API_URL', 'WHATSAPP_API_TOKEN', 'ADMIN_WHATSAPP_NUMBERS'];
    foreach ($required as $field) {
        if (empty($config[$field])) {
            $warnings[] = "Campo '$field' não configurado";
        }
    }
}

// Verificar tabelas
$tables = ['transactions', 'clients', 'charges', 'whatsapp_bot_logs'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            $success[] = "Tabela '$table' existe ($count registros)";
        } else {
            $errors[] = "Tabela '$table' não existe";
        }
    } catch (PDOException $e) {
        $errors[] = "Erro ao verificar tabela '$table': " . $e->getMessage();
    }
}

// Verificar diretório de comprovantes
$uploadDir = __DIR__ . '/' . ($config['COMPROVANTES_DIR'] ?? 'uploads/comprovantes/');
if (is_dir($uploadDir)) {
    $success[] = "Diretório de comprovantes existe: $uploadDir";
    if (is_writable($uploadDir)) {
        $success[] = "Diretório de comprovantes é gravável";
    } else {
        $errors[] = "Diretório de comprovantes não é gravável";
    }
} else {
    $errors[] = "Diretório de comprovantes não existe: $uploadDir";
}

// Verificar API do bot
$apiUrl = $config['WHATSAPP_API_URL'] ?? 'http://localhost:3001';
try {
    $ch = curl_init($apiUrl . '/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $statusData = json_decode($response, true);
        if (!empty($statusData['ready'])) {
            $success[] = "Bot WhatsApp está online e conectado";
        } else {
            $warnings[] = "Bot WhatsApp está online mas não conectado";
        }
    } else {
        $warnings[] = "Bot WhatsApp não está respondendo (HTTP $httpCode)";
    }
} catch (Exception $e) {
    $warnings[] = "Não foi possível conectar ao bot: " . $e->getMessage();
}

// Buscar últimas transações
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
    $totalTrans = $stmt->fetchColumn();
    $success[] = "Total de transações: $totalTrans";
} catch (PDOException $e) {
    $errors[] = "Erro ao contar transações: " . $e->getMessage();
}

// Buscar últimos logs
try {
    $stmt = $pdo->query("SELECT * FROM whatsapp_bot_logs ORDER BY created_at DESC LIMIT 10");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
    $warnings[] = "Erro ao buscar logs: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Sistema WhatsApp Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .debug-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .status-item {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .status-item.success {
            background: rgba(40, 167, 69, 0.1);
            border-color: #28a745;
        }
        .status-item.error {
            background: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
        }
        .status-item.warning {
            background: rgba(255, 193, 7, 0.1);
            border-color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="debug-card">
            <h1 class="mb-4">
                <i class="bi bi-bug me-2"></i>Debug - Sistema WhatsApp Financeiro
            </h1>
            
            <!-- Estatísticas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-success"><?php echo count($success); ?></h3>
                            <small>Sucessos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-warning"><?php echo count($warnings); ?></h3>
                            <small>Avisos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-danger"><?php echo count($errors); ?></h3>
                            <small>Erros</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status -->
            <h3 class="mb-3">Status do Sistema</h3>
            <?php foreach ($success as $msg): ?>
                <div class="status-item success">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endforeach; ?>
            
            <?php foreach ($warnings as $msg): ?>
                <div class="status-item warning">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endforeach; ?>
            
            <?php foreach ($errors as $msg): ?>
                <div class="status-item error">
                    <i class="bi bi-x-circle me-2"></i><?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Últimos Logs -->
            <?php if (!empty($logs)): ?>
            <h3 class="mt-4 mb-3">Últimos Logs</h3>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Telefone</th>
                            <th>Comando</th>
                            <th>Sucesso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['phone_number']); ?></td>
                                <td><?php echo htmlspecialchars($log['command'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($log['success']): ?>
                                        <span class="badge bg-success">Sim</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Não</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Ações -->
            <div class="mt-4 d-flex gap-2">
                <a href="whatsapp_admin.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>Voltar
                </a>
                <a href="setup_finance_tables.php" class="btn btn-secondary">
                    <i class="bi bi-database me-2"></i>Setup Tabelas
                </a>
                <button onclick="window.location.reload()" class="btn btn-info">
                    <i class="bi bi-arrow-clockwise me-2"></i>Recarregar
                </button>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once 'templates/footer.php'; ?>

