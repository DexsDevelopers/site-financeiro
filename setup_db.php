<?php
/**
 * Script de inicialização automática do Banco de Dados
 * Este script cria as tabelas necessárias para as Notificações Push
 */

require_once 'templates/header.php';

// Apenas admins ou usuários logados (ajuste conforme sua segurança)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    die("Acesso negado.");
}

$message = "";
$status = "";

if (isset($_POST['run_setup'])) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `push_subscriptions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `endpoint` varchar(2000) NOT NULL,
          `p256dh` varchar(255) NOT NULL,
          `auth` varchar(255) NOT NULL,
          `created_at` datetime NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `endpoint_hash` (`endpoint`(255)),
          KEY `user_id` (`user_id`),
          CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
        
        $status = "success";
        $message = "Tabela `push_subscriptions` criada ou já existente com sucesso!";
    } catch (PDOException $e) {
        $status = "danger";
        $message = "Erro ao criar tabela: " . $e->getMessage();
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card glass-panel featured shadow-lg">
                <div class="card-header border-0 bg-transparent py-4 text-center">
                    <h2 class="text-white">
                        <i class="bi bi-database-fill-gear me-2 text-info"></i>
                        Setup do Banco de Dados
                    </h2>
                </div>
                <div class="card-body p-4 text-center">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $status ?> mb-4" role="alert">
                            <i class="bi <?= $status === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-white-50 mb-4">
                        Este script irá preparar seu banco de dados para suportar as <strong>Notificações Push Nativas</strong>. 
                        Ele criará a tabela <code>push_subscriptions</code> caso ela ainda não exista.
                    </p>

                    <form method="POST">
                        <button type="submit" name="run_setup" class="btn btn-info btn-lg rounded-pill px-5 py-3 shadow-sm">
                            <i class="bi bi-play-fill me-2"></i> Iniciar Configuração
                        </button>
                    </form>
                    
                    <div class="mt-4">
                        <a href="dashboard.php" class="btn btn-link text-white-50">
                            <i class="bi bi-arrow-left me-1"></i> Voltar ao Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
