<?php
// setup_gestao_empresas.php - Criar tabelas para o módulo de Gestão de Empresas

require_once __DIR__ . '/includes/db_connect.php';

if (!$pdo) {
    die("Erro na conexão com o banco de dados.");
}

try {
    // 1. Tabela de Empresas
    $pdo->exec("CREATE TABLE IF NOT EXISTS ge_empresas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_pai INT NULL,
        nome VARCHAR(255) NOT NULL,
        logo VARCHAR(255) NULL,
        cnpj VARCHAR(20) NULL,
        descricao TEXT NULL,
        segmento VARCHAR(100) NULL,
        contato VARCHAR(255) NULL,
        endereco TEXT NULL,
        observacoes TEXT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_usuario),
        INDEX (id_pai),
        FOREIGN KEY (id_pai) REFERENCES ge_empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Garantir que a coluna id_pai existe caso a tabela já tenha sido criada antes
    try {
        $pdo->exec("ALTER TABLE ge_empresas ADD COLUMN id_pai INT NULL AFTER id_usuario, ADD INDEX (id_pai), ADD FOREIGN KEY (id_pai) REFERENCES ge_empresas(id) ON DELETE CASCADE");
    } catch (Exception $e) {
        // Ignora erro se a coluna já existir
    }

    // 2. Tabela Financeira das Empresas
    $pdo->exec("CREATE TABLE IF NOT EXISTS ge_financeiro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa INT NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(15,2) NOT NULL,
        tipo ENUM('entrada', 'saida') NOT NULL,
        categoria VARCHAR(100) NULL,
        data_transacao DATE NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_empresa),
        FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Tabela de Metas Financeiras
    $pdo->exec("CREATE TABLE IF NOT EXISTS ge_metas_financeiras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        valor_alvo DECIMAL(15,2) NOT NULL,
        valor_atual DECIMAL(15,2) DEFAULT 0,
        prazo DATE NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_empresa),
        FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Tabela de Ideias Futuras
    $pdo->exec("CREATE TABLE IF NOT EXISTS ge_ideias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT NULL,
        prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
        status ENUM('analise', 'andamento', 'concluida', 'cancelada') DEFAULT 'analise',
        notas_estrategicas TEXT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_empresa),
        FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Tabela de Conteúdo e Postagens
    $pdo->exec("CREATE TABLE IF NOT EXISTS ge_conteudo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT NULL,
        banco_ideias TEXT NULL,
        status ENUM('ideia', 'criacao', 'agendado', 'publicado') DEFAULT 'ideia',
        data_publicacao DATE NULL,
        plataforma VARCHAR(100) NULL,
        roteiro TEXT NULL,
        legenda TEXT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_empresa),
        FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. Tabela de Tarefas das Empresas
    $pdo->exec("CREATE TABLE IF NOT EXISTS ge_tarefas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        responsavel VARCHAR(100) NULL,
        prazo DATE NULL,
        prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
        status ENUM('pendente', 'andamento', 'concluida') DEFAULT 'pendente',
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_empresa),
        FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 7. Tabela de Redes Sociais
    $pdo->exec("CREATE TABLE IF NOT EXISTS ge_redes_sociais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_empresa INT NOT NULL,
        plataforma VARCHAR(100) NOT NULL,
        usuario VARCHAR(100) NULL,
        url_perfil VARCHAR(255) NULL,
        seguidores INT DEFAULT 0,
        seguindo INT DEFAULT 0,
        posts INT DEFAULT 0,
        engajamento DECIMAL(5,2) DEFAULT 0,
        observacoes TEXT NULL,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (id_empresa),
        FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Garantir colunas novas caso a tabela já exista
    try {
        $pdo->exec("ALTER TABLE ge_redes_sociais ADD COLUMN usuario VARCHAR(100) AFTER plataforma, ADD COLUMN seguindo INT DEFAULT 0 AFTER seguidores, ADD COLUMN posts INT DEFAULT 0 AFTER seguindo");
    } catch (Exception $e) {}

    echo "Tabelas de Gestão de Empresas criadas com sucesso!";
} catch (PDOException $e) {
    die("Erro ao criar tabelas: " . $e->getMessage());
}
