<?php
// criar_tabelas_contas.php
// Executa migração para suportar múltiplas contas (carteiras) e vincular transações a contas
// Uso: acesse este arquivo uma vez no navegador após deploy (estando logado) para criar/atualizar as estruturas.
// Segurança básica: requer sessão e usuário logado.

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Acesso negado. Faça login.";
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // IMPORTANTE: DDL em MySQL faz commit implícito. Não use transação aqui.
    $startedTx = false;

    // 1) Criar tabela contas (se não existir)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            -- algumas instalações antigas podem não ter todas as colunas; vamos garantir abaixo
            nome VARCHAR(100) NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 1.1) Garantir colunas obrigatórias na tabela contas
    $needIdUsuario = !$pdo->query("SHOW COLUMNS FROM contas LIKE 'id_usuario'")->fetch(PDO::FETCH_ASSOC);
    if ($needIdUsuario) {
        $pdo->exec("ALTER TABLE contas ADD COLUMN id_usuario INT NOT NULL AFTER id");
        $pdo->exec("CREATE INDEX idx_usuario ON contas (id_usuario)");
        // FK é opcional (pode falhar em hosts compartilhados); tentamos sem quebrar tudo
        try { $pdo->exec("ALTER TABLE contas ADD CONSTRAINT fk_contas_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE"); } catch (Throwable $e) {}
    }
    if (!$pdo->query("SHOW COLUMNS FROM contas LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE contas ADD COLUMN tipo VARCHAR(20) DEFAULT 'banco'");
    }
    if (!$pdo->query("SHOW COLUMNS FROM contas LIKE 'instituicao'")->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE contas ADD COLUMN instituicao VARCHAR(100) DEFAULT NULL");
    }
    if (!$pdo->query("SHOW COLUMNS FROM contas LIKE 'saldo_inicial'")->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE contas ADD COLUMN saldo_inicial DECIMAL(12,2) DEFAULT 0");
    }
    if (!$pdo->query("SHOW COLUMNS FROM contas LIKE 'cor'")->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE contas ADD COLUMN cor VARCHAR(7) DEFAULT NULL");
    }
    if (!$pdo->query("SHOW COLUMNS FROM contas LIKE 'nome'")->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE contas ADD COLUMN nome VARCHAR(100) NOT NULL");
    } else {
        // garantir NOT NULL em nome
        try { $pdo->exec("ALTER TABLE contas MODIFY COLUMN nome VARCHAR(100) NOT NULL"); } catch (Throwable $e) {}
    }
    // Garantir coluna criado_em
    if (!$pdo->query("SHOW COLUMNS FROM contas LIKE 'criado_em'")->fetch(PDO::FETCH_ASSOC)) {
        try {
            $pdo->exec("ALTER TABLE contas ADD COLUMN criado_em DATETIME DEFAULT CURRENT_TIMESTAMP");
        } catch (Throwable $e) {
            // fallback para hosts sem DEFAULT CURRENT_TIMESTAMP em DATETIME
            try { $pdo->exec("ALTER TABLE contas ADD COLUMN criado_em DATETIME NULL"); } catch (Throwable $e2) {}
        }
    }
    // Ajuste opcional: algumas instalações antigas possuem 'codigo_conta' UNIQUE com default ''.
    $hasCodigoConta = (bool)$pdo->query("SHOW COLUMNS FROM contas LIKE 'codigo_conta'")->fetch(PDO::FETCH_ASSOC);
    if ($hasCodigoConta) {
        // Garantir que aceite NULL (para evitar duplicidade de string vazia)
        try { $pdo->exec("ALTER TABLE contas MODIFY COLUMN codigo_conta VARCHAR(64) NULL"); } catch (Throwable $e) {}
    }

    // 2) Adicionar coluna id_conta em transacoes (se não existir)
    $colExists = $pdo->query("SHOW COLUMNS FROM transacoes LIKE 'id_conta'")->fetch(PDO::FETCH_ASSOC);
    if (!$colExists) {
        $pdo->exec("ALTER TABLE transacoes ADD COLUMN id_conta INT NULL AFTER id_categoria, ADD INDEX idx_id_conta (id_conta)");
        // Opcional: adicionar FK (se banco permitir sem downtime em produção)
        // $pdo->exec("ALTER TABLE transacoes ADD CONSTRAINT fk_transacoes_conta FOREIGN KEY (id_conta) REFERENCES contas(id) ON DELETE SET NULL");
    }

    // 3) Backfill: criar conta 'Geral' para cada usuário que não tenha nenhuma
    // e associar transações sem id_conta a essa conta
    // Buscar usuários com transações sem id_conta
    $usuariosSemConta = $pdo->query("SELECT DISTINCT id_usuario FROM transacoes WHERE id_conta IS NULL")->fetchAll(PDO::FETCH_COLUMN);

    // Iniciar transação apenas para DML do backfill
    if (!empty($usuariosSemConta)) {
        $pdo->beginTransaction();
        $startedTx = true;
    }

    foreach ($usuariosSemConta as $uid) {
        // Verificar se já existe alguma conta
        $stmt = $pdo->prepare("SELECT id FROM contas WHERE id_usuario = ? LIMIT 1");
        $stmt->execute([$uid]);
        $contaId = $stmt->fetchColumn();

        if (!$contaId) {
            // Criar conta Geral
            if ($hasCodigoConta) {
                // Gerar um código único e não vazio
                $codigo = bin2hex(random_bytes(8)); // 16 chars
                $stmt = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, saldo_inicial, codigo_conta) VALUES (?, 'Geral', 'dinheiro', 0, ?)");
                $stmt->execute([$uid, $codigo]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO contas (id_usuario, nome, tipo, saldo_inicial) VALUES (?, 'Geral', 'dinheiro', 0)");
                $stmt->execute([$uid]);
            }
            $contaId = $pdo->lastInsertId();
        }

        // Atualizar transações sem conta para apontar para a conta criada/existente
        $stmt = $pdo->prepare("UPDATE transacoes SET id_conta = ? WHERE id_usuario = ? AND (id_conta IS NULL)");
        $stmt->execute([$contaId, $uid]);
    }

    if ($startedTx && $pdo->inTransaction()) {
        $pdo->commit();
    }
    echo "Migração concluída com sucesso.\n";
    echo "- Tabela 'contas' criada/verificada.\n";
    echo "- Coluna 'id_conta' em 'transacoes' criada/verificada.\n";
    echo "- Backfill aplicado (transações antigas vinculadas à conta 'Geral').\n";
} catch (Throwable $e) {
    if (isset($startedTx) && $startedTx && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "Falha na migração: " . $e->getMessage();
}
?>


