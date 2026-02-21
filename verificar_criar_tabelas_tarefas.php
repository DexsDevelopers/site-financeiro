<?php
/**
 * verificar_criar_tabelas_tarefas.php
 * Verifica e cria automaticamente todas as tabelas necessárias para o sistema de tarefas
 */

require_once 'includes/db_connect.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verificador de Tabelas - Sistema de Tarefas</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
        }
        .table-check {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        .table-check.ok {
            background: #e8f5e9;
            border-left-color: #4caf50;
        }
        .table-check.created {
            background: #fff3e0;
            border-left-color: #ff9800;
        }
        .table-check.error {
            background: #ffebee;
            border-left-color: #f44336;
        }
        .status {
            font-weight: bold;
            margin-left: 10px;
        }
        .status.ok { color: #4caf50; }
        .status.created { color: #ff9800; }
        .status.error { color: #f44336; }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            text-align: center;
        }
        .summary h2 {
            color: #667eea;
            margin-top: 0;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>✓ Verificador de Tabelas - Sistema de Tarefas</h1>";

// Array com todas as tabelas necessárias
$tabelas = [
    'tarefas' => "
        CREATE TABLE IF NOT EXISTS tarefas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            id_empresa INT NULL,
            descricao TEXT NOT NULL,
            prioridade ENUM('Alta', 'Média', 'Baixa') DEFAULT 'Média',
            data_limite DATE NULL,
            hora_inicio TIME NULL,
            hora_fim TIME NULL,
            status ENUM('pendente', 'concluido') DEFAULT 'pendente',
            tempo_estimado INT DEFAULT NULL,
            ordem INT DEFAULT 0,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (id_empresa) REFERENCES ge_empresas(id) ON DELETE SET NULL,
            INDEX idx_usuario (id_usuario),
            INDEX idx_empresa (id_empresa),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    'subtarefas' => "
        CREATE TABLE IF NOT EXISTS subtarefas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_tarefa_principal INT NOT NULL,
            descricao TEXT NOT NULL,
            status ENUM('pendente', 'concluida') DEFAULT 'pendente',
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_tarefa_principal) REFERENCES tarefas(id) ON DELETE CASCADE,
            INDEX idx_tarefa (id_tarefa_principal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    'rotinas_fixas' => "
        CREATE TABLE IF NOT EXISTS rotinas_fixas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            horario_sugerido TIME NULL,
            prioridade ENUM('Alta', 'Média', 'Baixa') DEFAULT 'Média',
            descricao TEXT NULL,
            ordem INT DEFAULT 0,
            ativo BOOLEAN DEFAULT TRUE,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            UNIQUE KEY unique_rotina_usuario (id_usuario, nome),
            INDEX idx_usuario (id_usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    'rotina_controle_diario' => "
        CREATE TABLE IF NOT EXISTS rotina_controle_diario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            id_rotina_fixa INT NOT NULL,
            data_execucao DATE NOT NULL,
            status ENUM('pendente', 'concluido', 'pulado') DEFAULT 'pendente',
            horario_execucao TIME NULL,
            observacoes TEXT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (id_rotina_fixa) REFERENCES rotinas_fixas(id) ON DELETE CASCADE,
            UNIQUE KEY unique_controle_dia (id_usuario, id_rotina_fixa, data_execucao),
            INDEX idx_data (data_execucao),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

// Scripts extras para adicionar colunas em tabelas existentes
$scripts_extras = [
    "ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS id_empresa INT NULL AFTER id_usuario",
    "ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS hora_inicio TIME NULL AFTER data_limite",
    "ALTER TABLE tarefas ADD COLUMN IF NOT EXISTS hora_fim TIME NULL AFTER hora_inicio",
    "ALTER TABLE rotinas_fixas ADD COLUMN IF NOT EXISTS prioridade ENUM('Alta', 'Média', 'Baixa') DEFAULT 'Média' AFTER horario_sugerido",
];

$tabelas_ok = 0;
$tabelas_criadas = 0;
$tabelas_erro = 0;

// Verificar e criar tabelas
foreach ($tabelas as $nome_tabela => $sql) {
    try {
        // Verificar se tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE '$nome_tabela'");
        $existe = $stmt->rowCount() > 0;

        if ($existe) {
            echo "<div class='table-check ok'>
                    ✓ <strong>$nome_tabela</strong>
                    <span class='status ok'>Existe</span>
                  </div>";
            $tabelas_ok++;
        }
        else {
            // Criar tabela
            $pdo->exec($sql);
            echo "<div class='table-check created'>
                    + <strong>$nome_tabela</strong>
                    <span class='status created'>Criada com sucesso</span>
                  </div>";
            $tabelas_criadas++;
        }
    }
    catch (Exception $e) {
        echo "<div class='table-check error'>
                ✗ <strong>$nome_tabela</strong>
                <span class='status error'>Erro: " . htmlspecialchars($e->getMessage()) . "</span>
              </div>";
        $tabelas_erro++;
    }
}

// Executar scripts extras para colunas novas
foreach ($scripts_extras as $sql_extra) {
    try {
        $pdo->exec($sql_extra);
    }
    catch (Exception $e) {
    // Silencioso se der erro
    }
}

// Resumo
echo "<div class='summary'>
        <h2>📊 Resumo da Verificação</h2>
        <p><strong>✓ Tabelas Existentes:</strong> <span style='color: #4caf50; font-size: 18px;'>$tabelas_ok</span></p>
        <p><strong>+ Tabelas Criadas:</strong> <span style='color: #ff9800; font-size: 18px;'>$tabelas_criadas</span></p>
        <p><strong>✗ Erros:</strong> <span style='color: #f44336; font-size: 18px;'>$tabelas_erro</span></p>";

if ($tabelas_erro === 0) {
    echo "<p style='color: #4caf50; font-size: 16px; font-weight: bold;'>✓ Sistema pronto para usar!</p>";
}
else {
    echo "<p style='color: #f44336; font-size: 16px; font-weight: bold;'>✗ Verifique os erros acima</p>";
}

echo "</div>
    </div>
</body>
</html>";
?>
