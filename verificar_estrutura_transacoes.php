<?php
// verificar_estrutura_transacoes.php - Verificar e corrigir estrutura da tabela transacoes

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🔍 Verificação da Estrutura da Tabela 'transacoes'</h2>";

// Verificar se a tabela existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'transacoes'");
    $tabelaExiste = $stmt->fetch();
    
    if (!$tabelaExiste) {
        echo "<div class='alert alert-danger'>";
        echo "<h4>❌ Tabela 'transacoes' não existe!</h4>";
        echo "<p>Vou criar a tabela com a estrutura correta...</p>";
        echo "</div>";
        
        // Criar tabela transacoes
        $sql = "
        CREATE TABLE transacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            data DATE NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            tipo ENUM('receita', 'despesa') NOT NULL,
            categoria VARCHAR(100) DEFAULT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (id_usuario),
            INDEX idx_data (data),
            INDEX idx_tipo (tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql);
        echo "<div class='alert alert-success'>";
        echo "<h4>✅ Tabela 'transacoes' criada com sucesso!</h4>";
        echo "</div>";
        
    } else {
        echo "<div class='alert alert-success'>";
        echo "<h4>✅ Tabela 'transacoes' existe</h4>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Erro ao verificar/criar tabela:</h4>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// Verificar estrutura atual
echo "<h3>📋 Estrutura Atual da Tabela:</h3>";

try {
    $stmt = $pdo->query("DESCRIBE transacoes");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='table-responsive'>";
    echo "<table class='table table-striped'>";
    echo "<thead><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($colunas as $coluna) {
        $cor = '';
        if ($coluna['Field'] === 'id') $cor = 'table-success';
        if ($coluna['Field'] === 'id_usuario') $cor = 'table-info';
        if ($coluna['Field'] === 'data') $cor = 'table-warning';
        if ($coluna['Field'] === 'descricao') $cor = 'table-warning';
        if ($coluna['Field'] === 'valor') $cor = 'table-warning';
        if ($coluna['Field'] === 'tipo') $cor = 'table-warning';
        
        echo "<tr class='$cor'>";
        echo "<td><strong>" . $coluna['Field'] . "</strong></td>";
        echo "<td>" . $coluna['Type'] . "</td>";
        echo "<td>" . $coluna['Null'] . "</td>";
        echo "<td>" . $coluna['Key'] . "</td>";
        echo "<td>" . ($coluna['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $coluna['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao verificar estrutura:</strong> " . $e->getMessage() . "</p>";
}

// Verificar se há problemas na estrutura
echo "<h3>🔧 Análise de Problemas:</h3>";

$problemas = [];
$colunasObrigatorias = ['id', 'id_usuario', 'data', 'descricao', 'valor', 'tipo'];

foreach ($colunasObrigatorias as $coluna) {
    $encontrada = false;
    foreach ($colunas as $col) {
        if ($col['Field'] === $coluna) {
            $encontrada = true;
            break;
        }
    }
    
    if (!$encontrada) {
        $problemas[] = "Coluna '$coluna' não encontrada";
    }
}

if (empty($problemas)) {
    echo "<div class='alert alert-success'>";
    echo "<h5>✅ Estrutura da tabela está correta!</h5>";
    echo "<p>Todas as colunas obrigatórias estão presentes.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-warning'>";
    echo "<h5>⚠️ Problemas encontrados:</h5>";
    echo "<ul>";
    foreach ($problemas as $problema) {
        echo "<li>$problema</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Testar inserção de transação de teste
echo "<h3>🧪 Teste de Inserção:</h3>";

try {
    // Remover transações de teste anteriores
    $stmt = $pdo->prepare("DELETE FROM transacoes WHERE id_usuario = ? AND categoria = 'Teste Estrutura'");
    $stmt->execute([$userId]);
    
    // Inserir transação de teste
    $stmt = $pdo->prepare("
        INSERT INTO transacoes (
            id_usuario, 
            data, 
            descricao, 
            valor, 
            tipo, 
            categoria, 
            data_criacao
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $resultado = $stmt->execute([
        $userId,
        '2025-01-15',
        'TESTE ESTRUTURA - PIX RECEBIDO',
        150.00,
        'receita',
        'Teste Estrutura'
    ]);
    
    if ($resultado) {
        $idInserido = $pdo->lastInsertId();
        echo "<div class='alert alert-success'>";
        echo "<h5>✅ Inserção bem-sucedida!</h5>";
        echo "<p><strong>ID da transação:</strong> $idInserido</p>";
        echo "</div>";
        
        // Verificar se a transação foi inserida corretamente
        $stmt = $pdo->prepare("SELECT * FROM transacoes WHERE id = ?");
        $stmt->execute([$idInserido]);
        $transacao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transacao) {
            echo "<div class='alert alert-info'>";
            echo "<h5>📋 Dados da transação inserida:</h5>";
            echo "<pre>" . json_encode($transacao, JSON_PRETTY_PRINT) . "</pre>";
            echo "</div>";
        }
        
        // Remover transação de teste
        $stmt = $pdo->prepare("DELETE FROM transacoes WHERE id = ?");
        $stmt->execute([$idInserido]);
        echo "<p>🗑️ <strong>Transação de teste removida</strong></p>";
        
    } else {
        echo "<div class='alert alert-danger'>";
        echo "<h5>❌ Falha na inserção</h5>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Erro na inserção:</h5>";
    echo "<p><strong>Mensagem:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Código:</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>SQL State:</strong> " . $e->errorInfo[0] . "</p>";
    echo "</div>";
}

// Verificar índices
echo "<h3>📊 Índices da Tabela:</h3>";

try {
    $stmt = $pdo->query("SHOW INDEX FROM transacoes");
    $indices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($indices)) {
        echo "<p>Nenhum índice encontrado.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>Nome</th><th>Coluna</th><th>Único</th><th>Tipo</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($indices as $indice) {
            echo "<tr>";
            echo "<td>" . $indice['Key_name'] . "</td>";
            echo "<td>" . $indice['Column_name'] . "</td>";
            echo "<td>" . ($indice['Non_unique'] ? 'Não' : 'Sim') . "</td>";
            echo "<td>" . $indice['Index_type'] . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao verificar índices:</strong> " . $e->getMessage() . "</p>";
}

// Estatísticas da tabela
echo "<h3>📈 Estatísticas da Tabela:</h3>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transacoes");
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as usuario FROM transacoes WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $usuario = $stmt->fetchColumn();
    
    echo "<div class='row'>";
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-body'>";
    echo "<h5 class='card-title'>Total de Transações</h5>";
    echo "<h2 class='text-primary'>$total</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-body'>";
    echo "<h5 class='card-title'>Suas Transações</h5>";
    echo "<h2 class='text-success'>$usuario</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao obter estatísticas:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='debug_importacao_csv.php'>← Debug Importação CSV</a></p>";
echo "<p><a href='importar_extrato_csv.php'>← Importação CSV</a></p>";
?>
