<?php
// setup_finance_tables.php - Script de criação de tabelas para sistema financeiro WhatsApp
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Tabelas Financeiras WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-dark text-white">
    <div class="container py-5">
        <h1 class="mb-4"><i class="bi bi-database me-2"></i>Setup - Tabelas Financeiras WhatsApp</h1>
        
        <?php
        $errors = [];
        $success = [];
        
        try {
            // Criar tabela de transações
            $sql1 = "CREATE TABLE IF NOT EXISTS transactions (
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
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $pdo->exec($sql1);
            $success[] = "Tabela 'transactions' criada/verificada com sucesso";
            
        } catch (PDOException $e) {
            $errors[] = "Erro ao criar tabela 'transactions': " . $e->getMessage();
        }
        
        try {
            // Criar tabela de clientes
            $sql2 = "CREATE TABLE IF NOT EXISTS clients (
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
            
            $pdo->exec($sql2);
            $success[] = "Tabela 'clients' criada/verificada com sucesso";
            
        } catch (PDOException $e) {
            $errors[] = "Erro ao criar tabela 'clients': " . $e->getMessage();
        }
        
        try {
            // Criar tabela de cobranças
            $sql3 = "CREATE TABLE IF NOT EXISTS charges (
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
            
            $pdo->exec($sql3);
            $success[] = "Tabela 'charges' criada/verificada com sucesso";
            
        } catch (PDOException $e) {
            $errors[] = "Erro ao criar tabela 'charges': " . $e->getMessage();
        }
        
        try {
            // Criar tabela de logs do WhatsApp
            $sql4 = "CREATE TABLE IF NOT EXISTS whatsapp_bot_logs (
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
            
            $pdo->exec($sql4);
            $success[] = "Tabela 'whatsapp_bot_logs' criada/verificada com sucesso";
            
        } catch (PDOException $e) {
            $errors[] = "Erro ao criar tabela 'whatsapp_bot_logs': " . $e->getMessage();
        }
        
        // Criar diretório de comprovantes
        $uploadDir = __DIR__ . '/uploads/comprovantes';
        if (!is_dir($uploadDir)) {
            if (mkdir($uploadDir, 0755, true)) {
                $success[] = "Diretório 'uploads/comprovantes' criado";
                
                // Criar .htaccess para proteger
                file_put_contents($uploadDir . '/.htaccess', "deny from all\n");
                $success[] = "Arquivo .htaccess criado em uploads/comprovantes";
            } else {
                $errors[] = "Não foi possível criar o diretório 'uploads/comprovantes'";
            }
        } else {
            $success[] = "Diretório 'uploads/comprovantes' já existe";
        }
        
        // Verificar se tabelas existem
        $tables = ['transactions', 'clients', 'charges', 'whatsapp_bot_logs'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                    $count = $stmt->fetchColumn();
                    $success[] = "Tabela '$table' existe com $count registro(s)";
                }
            } catch (PDOException $e) {
                $errors[] = "Erro ao verificar tabela '$table': " . $e->getMessage();
            }
        }
        ?>
        
        <div class="row">
            <div class="col-md-8">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle me-2"></i>Sucessos:</h5>
                        <ul class="mb-0">
                            <?php foreach ($success as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-x-circle me-2"></i>Erros:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($errors) && !empty($success)): ?>
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>Próximos Passos:</h5>
                        <ol>
                            <li>Configure o arquivo <code>config.json</code> com seus dados</li>
                            <li>Configure o arquivo <code>.env</code> do bot WhatsApp</li>
                            <li>Inicie o bot WhatsApp: <code>npm run dev</code></li>
                            <li>Teste os comandos enviando <code>/menu</code> via WhatsApp</li>
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
        </div>
    </div>
</body>
</html>

