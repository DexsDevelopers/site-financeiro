<?php
// setup_whatsapp_auth.php - Criar tabela de sessões WhatsApp
require_once 'includes/db_connect.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Autenticação WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-dark text-white">
    <div class="container py-5">
        <h1 class="mb-4"><i class="bi bi-shield-lock me-2"></i>Setup - Autenticação WhatsApp</h1>
        
        <?php
        $errors = [];
        $success = [];
        
        try {
            // Criar tabela de sessões WhatsApp
            $sql1 = "CREATE TABLE IF NOT EXISTS whatsapp_sessions (
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
            
            $pdo->exec($sql1);
            $success[] = "Tabela 'whatsapp_sessions' criada/verificada com sucesso";
            
        } catch (PDOException $e) {
            $errors[] = "Erro ao criar tabela 'whatsapp_sessions': " . $e->getMessage();
        }
        
        try {
            // Verificar se coluna telefone_e164 existe na tabela usuarios
            $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telefone_e164'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN telefone_e164 VARCHAR(20) NULL AFTER telefone");
                $success[] = "Coluna 'telefone_e164' adicionada à tabela 'usuarios'";
            } else {
                $success[] = "Coluna 'telefone_e164' já existe";
            }
        } catch (PDOException $e) {
            $errors[] = "Erro ao verificar/adicionar coluna 'telefone_e164': " . $e->getMessage();
        }
        
        // Verificar se tabela existe
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'whatsapp_sessions'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM whatsapp_sessions");
                $count = $stmt->fetchColumn();
                $success[] = "Tabela 'whatsapp_sessions' existe com $count sessão(ões) ativa(s)";
            }
        } catch (PDOException $e) {
            $errors[] = "Erro ao verificar tabela: " . $e->getMessage();
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
                        <h5><i class="bi bi-info-circle me-2"></i>Sistema de Autenticação:</h5>
                        <p>O sistema agora suporta login via WhatsApp. Os usuários podem:</p>
                        <ul>
                            <li>Fazer login com <code>!login EMAIL SENHA</code></li>
                            <li>Fazer login com código de acesso (se configurado)</li>
                            <li>Verificar status com <code>!status</code></li>
                            <li>Fazer logout com <code>!logout</code></li>
                        </ul>
                        <p class="mb-0"><strong>Importante:</strong> Todas as transações serão associadas à conta do usuário logado.</p>
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



