<?php
// /admin/db_migration_ge.php
require_once 'header_admin.php';

echo "<div class='container-fluid py-4'>";
echo "<div class='admin-card'>";
echo "<div class='card-header'>";
echo "<h3 class='mb-0'><i class='bi bi-database-fill-add me-2'></i>Migração: Gestão de Empresas</h3>";
echo "</div>";
echo "<div class='card-body'>";
echo "<pre style='background: #1a1a1a; color: #0f0; padding: 1rem; border-radius: 8px; overflow-x: auto;'>";

try {
    $pdo->beginTransaction();

    // 1. Criar tabela ge_empresas
    echo "✅ Criando tabela 'ge_empresas'...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ge_empresas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            segmento VARCHAR(50) NULL,
            cor_tema VARCHAR(20) DEFAULT '#e50914',
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (id_usuario),
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela 'ge_empresas' pronta.\n";

    // 2. Criar tabela ge_financeiro
    echo "\n✅ Criando tabela 'ge_financeiro'...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ge_financeiro (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_empresa INT NOT NULL,
            tipo ENUM('receita', 'despesa') NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            categoria VARCHAR(50) NULL,
            data_transacao DATE NOT NULL,
            status ENUM('pago', 'pendente') DEFAULT 'pago',
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_empresa (id_empresa),
            FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela 'ge_financeiro' pronta.\n";

    // 3. Criar tabela ge_tarefas
    echo "\n✅ Criando tabela 'ge_tarefas'...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ge_tarefas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_empresa INT NOT NULL,
            titulo VARCHAR(150) NOT NULL,
            descricao TEXT NULL,
            prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
            status ENUM('pendente', 'em_progresso', 'concluida') DEFAULT 'pendente',
            data_limite DATE NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_empresa (id_empresa),
            FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela 'ge_tarefas' pronta.\n";

    // 4. Criar tabela ge_social_stats
    echo "\n✅ Criando tabela 'ge_social_stats'...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ge_social_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_empresa INT NOT NULL,
            rede_social VARCHAR(50) NOT NULL,
            usuario VARCHAR(100) NOT NULL,
            seguidores INT DEFAULT 0,
            engajamento DECIMAL(5,2) DEFAULT 0.00,
            data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_empresa (id_empresa),
            FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela 'ge_social_stats' pronta.\n";

    $pdo->commit();
    echo "\n✅ Migração concluída com sucesso!\n";
    echo "\n🔄 <a href='admin_empresas.php' style='color: #0f0;'>← Ir para Gerenciamento de Empresas</a>\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n❌ Erro na migração: " . htmlspecialchars($e->getMessage()) . "\n";
}

echo "</pre>";
echo "</div>";
echo "</div>";
echo "</div>";

require_once 'footer_admin.php';
?>
