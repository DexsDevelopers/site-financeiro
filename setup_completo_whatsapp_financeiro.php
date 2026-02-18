<?php
// setup_completo_whatsapp_financeiro.php - Setup completo do sistema financeiro WhatsApp
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Completo - Sistema Financeiro WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .card {
            margin-bottom: 20px;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body class="bg-dark text-white">
    <div class="container py-5">
        <h1 class="mb-4"><i class="bi bi-database-check me-2"></i>Setup Completo - Sistema Financeiro WhatsApp</h1>
        
        <?php
        $errors = [];
        $success = [];
        $warnings = [];
        $info = [];
        
        try {
            // ============================================
            // 1. TABELA: transactions
            // ============================================
            try {
                $sql = "CREATE TABLE IF NOT EXISTS transactions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type ENUM('receita', 'despesa') NOT NULL,
                    value DECIMAL(10,2) NOT NULL,
                    description VARCHAR(255) NOT NULL,
                    category VARCHAR(100),
                    client_id INT,
                    receipt_path VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_by VARCHAR(20),
                    id_usuario INT,
                    INDEX idx_date (created_at),
                    INDEX idx_client (client_id),
                    INDEX idx_usuario (id_usuario),
                    INDEX idx_type (type),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $pdo->exec($sql);
                $success[] = "Tabela 'transactions' criada/verificada";
                
                // Verificar se coluna id_usuario existe
                $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'id_usuario'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE transactions ADD COLUMN id_usuario INT AFTER created_by");
                    $pdo->exec("ALTER TABLE transactions ADD INDEX idx_usuario (id_usuario)");
                    $success[] = "Coluna 'id_usuario' adicionada à tabela 'transactions'";
                }
                
                // Estatísticas da tabela
                $stmt = $pdo->query("SELECT COUNT(*) as total, 
                                    SUM(CASE WHEN id_usuario IS NULL THEN 1 ELSE 0 END) as sem_usuario,
                                    SUM(CASE WHEN id_usuario IS NOT NULL THEN 1 ELSE 0 END) as com_usuario
                                    FROM transactions");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($stats['total'] > 0) {
                    $info[] = "Transações: {$stats['total']} total | {$stats['com_usuario']} com usuário | {$stats['sem_usuario']} sem usuário";
                    if ($stats['sem_usuario'] > 0) {
                        $warnings[] = "Existem {$stats['sem_usuario']} transações sem usuário associado";
                    }
                }
                
            } catch (PDOException $e) {
                $errors[] = "Erro na tabela 'transactions': " . $e->getMessage();
            }
            
            // ============================================
            // 2. TABELA: clients
            // ============================================
            try {
                $sql = "CREATE TABLE IF NOT EXISTS clients (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    phone VARCHAR(20),
                    email VARCHAR(100),
                    whatsapp_number VARCHAR(20),
                    id_usuario INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_whatsapp (whatsapp_number),
                    INDEX idx_usuario (id_usuario)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $pdo->exec($sql);
                $success[] = "Tabela 'clients' criada/verificada";
                
                // Verificar se coluna id_usuario existe
                $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'id_usuario'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE clients ADD COLUMN id_usuario INT AFTER whatsapp_number");
                    $pdo->exec("ALTER TABLE clients ADD INDEX idx_usuario (id_usuario)");
                    $success[] = "Coluna 'id_usuario' adicionada à tabela 'clients'";
                }
                
            } catch (PDOException $e) {
                $errors[] = "Erro na tabela 'clients': " . $e->getMessage();
            }
            
            // ============================================
            // 3. TABELA: charges
            // ============================================
            try {
                $sql = "CREATE TABLE IF NOT EXISTS charges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    value DECIMAL(10,2) NOT NULL,
                    due_date DATE NOT NULL,
                    description VARCHAR(255),
                    status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
                    paid_at TIMESTAMP NULL,
                    id_usuario INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_client (client_id),
                    INDEX idx_status (status),
                    INDEX idx_due_date (due_date),
                    INDEX idx_usuario (id_usuario)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $pdo->exec($sql);
                $success[] = "Tabela 'charges' criada/verificada";
                
                // Verificar se coluna id_usuario existe
                $stmt = $pdo->query("SHOW COLUMNS FROM charges LIKE 'id_usuario'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE charges ADD COLUMN id_usuario INT AFTER status");
                    $pdo->exec("ALTER TABLE charges ADD INDEX idx_usuario (id_usuario)");
                    $success[] = "Coluna 'id_usuario' adicionada à tabela 'charges'";
                }
                
            } catch (PDOException $e) {
                $errors[] = "Erro na tabela 'charges': " . $e->getMessage();
            }
            
            // ============================================
            // 4. TABELA: whatsapp_bot_logs
            // ============================================
            try {
                $sql = "CREATE TABLE IF NOT EXISTS whatsapp_bot_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    phone_number VARCHAR(20) NOT NULL,
                    command VARCHAR(50),
                    message TEXT,
                    response TEXT,
                    success TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_phone (phone_number),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $pdo->exec($sql);
                $success[] = "Tabela 'whatsapp_bot_logs' criada/verificada";
                
            } catch (PDOException $e) {
                $errors[] = "Erro na tabela 'whatsapp_bot_logs': " . $e->getMessage();
            }
            
            // ============================================
            // 5. TABELA: whatsapp_sessions
            // ============================================
            try {
                $sql = "CREATE TABLE IF NOT EXISTS whatsapp_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    phone_number VARCHAR(20) NOT NULL,
                    user_id INT NOT NULL,
                    logged_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    is_active TINYINT(1) DEFAULT 1,
                    UNIQUE KEY unique_phone (phone_number),
                    INDEX idx_user (user_id),
                    INDEX idx_phone (phone_number),
                    INDEX idx_active (is_active),
                    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $pdo->exec($sql);
                $success[] = "Tabela 'whatsapp_sessions' criada/verificada";
                
            } catch (PDOException $e) {
                $errors[] = "Erro na tabela 'whatsapp_sessions': " . $e->getMessage();
            }
            
            // ============================================
            // 6. DIRETÓRIO DE COMPROVANTES
            // ============================================
            $uploadDir = __DIR__ . '/uploads/comprovantes';
            if (!is_dir($uploadDir)) {
                if (mkdir($uploadDir, 0755, true)) {
                    $success[] = "Diretório 'uploads/comprovantes' criado";
                    
                    // Criar .htaccess para proteger
                    file_put_contents($uploadDir . '/.htaccess', "deny from all\n");
                    $success[] = "Arquivo .htaccess criado em uploads/comprovantes";
                } else {
                    $warnings[] = "Não foi possível criar o diretório 'uploads/comprovantes'";
                }
            } else {
                $info[] = "Diretório 'uploads/comprovantes' já existe";
            }
            
            // ============================================
            // 7. VERIFICAÇÕES E ESTATÍSTICAS
            // ============================================
            $tables = ['transactions', 'clients', 'charges', 'whatsapp_bot_logs', 'whatsapp_sessions'];
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                        $count = $stmt->fetchColumn();
                        $info[] = "Tabela '$table': $count registro(s)";
                    }
                } catch (PDOException $e) {
                    $warnings[] = "Erro ao verificar tabela '$table': " . $e->getMessage();
                }
            }
            
            // Verificar estrutura da tabela transactions
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM transactions");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $columnNames = array_column($columns, 'Field');
                
                if (in_array('id_usuario', $columnNames)) {
                    $info[] = "✅ Coluna 'id_usuario' existe na tabela 'transactions'";
                } else {
                    $errors[] = "❌ Coluna 'id_usuario' NÃO existe na tabela 'transactions'";
                }
                
                // Verificar índices
                $stmt = $pdo->query("SHOW INDEXES FROM transactions WHERE Key_name = 'idx_usuario'");
                if ($stmt->rowCount() > 0) {
                    $info[] = "✅ Índice 'idx_usuario' existe na tabela 'transactions'";
                } else {
                    $warnings[] = "⚠️ Índice 'idx_usuario' não existe na tabela 'transactions'";
                }
                
            } catch (PDOException $e) {
                $warnings[] = "Erro ao verificar estrutura de 'transactions': " . $e->getMessage();
            }
            
            // Verificar transações sem usuário
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE id_usuario IS NULL");
                $semUsuario = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                if ($semUsuario > 0) {
                    $warnings[] = "⚠️ Existem $semUsuario transação(ões) sem usuário associado. Essas transações não aparecerão no saldo do WhatsApp.";
                }
            } catch (PDOException $e) {
                // Ignorar se a tabela não existir ainda
            }
            
        } catch (Exception $e) {
            $errors[] = "Erro geral: " . $e->getMessage();
        }
        ?>
        
        <div class="row">
            <div class="col-md-10">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle me-2"></i>Sucessos (<?php echo count($success); ?>):</h5>
                        <ul class="mb-0">
                            <?php foreach ($success as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($warnings)): ?>
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i>Avisos (<?php echo count($warnings); ?>):</h5>
                        <ul class="mb-0">
                            <?php foreach ($warnings as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($info)): ?>
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>Informações:</h5>
                        <ul class="mb-0">
                            <?php foreach ($info as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-x-circle me-2"></i>Erros (<?php echo count($errors); ?>):</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($errors) && !empty($success)): ?>
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle-fill me-2"></i>Sistema Configurado com Sucesso!</h5>
                        <p class="mb-2">Todas as tabelas necessárias foram criadas/verificadas.</p>
                        <hr>
                        <h6>Próximos Passos:</h6>
                        <ol>
                            <li>Certifique-se de que o bot WhatsApp está rodando</li>
                            <li>Faça login via WhatsApp: <code>!login EMAIL SENHA</code></li>
                            <li>Teste os comandos: <code>!menu</code>, <code>!receita</code>, <code>!saldo</code></li>
                            <li>Verifique se o saldo está sendo calculado corretamente</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-2"></i>Voltar ao Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn btn-secondary">
                <i class="bi bi-arrow-clockwise me-2"></i>Recarregar
            </button>
            <a href="setup_whatsapp_auth.php" class="btn btn-info">
                <i class="bi bi-shield-lock me-2"></i>Setup Autenticação
            </a>
        </div>
    </div>
</body>
</html>



