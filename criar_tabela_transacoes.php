<?php
// criar_tabela_transacoes.php - Criar tabela transacoes com estrutura correta

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🗄️ Criar Tabela 'transacoes'</h2>";

// Verificar se a tabela já existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'transacoes'");
    $tabelaExiste = $stmt->fetch();
    
    if ($tabelaExiste) {
        echo "<div class='alert alert-warning'>";
        echo "<h4>⚠️ Tabela 'transacoes' já existe!</h4>";
        echo "<p>Deseja recriar a tabela? Isso irá apagar todos os dados existentes.</p>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='recriar' class='btn btn-danger'>Recriar Tabela (APAGAR DADOS)</button>";
        echo "</form>";
        echo "</div>";
        
        if (isset($_POST['recriar'])) {
            echo "<div class='alert alert-info'>";
            echo "<h4>🔄 Recriando tabela...</h4>";
            echo "</div>";
            
            // Dropar tabela existente
            $pdo->exec("DROP TABLE IF EXISTS transacoes");
            echo "<p>✅ Tabela existente removida</p>";
            
            // Criar nova tabela
            criarTabelaTransacoes();
        }
        
    } else {
        echo "<div class='alert alert-info'>";
        echo "<h4>ℹ️ Tabela 'transacoes' não existe</h4>";
        echo "<p>Vou criar a tabela com a estrutura correta...</p>";
        echo "</div>";
        
        criarTabelaTransacoes();
    }
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Erro ao verificar tabela:</h4>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

function criarTabelaTransacoes() {
    global $pdo;
    
    try {
        // SQL para criar a tabela transacoes
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
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Índices para performance
            INDEX idx_usuario (id_usuario),
            INDEX idx_data (data),
            INDEX idx_tipo (tipo),
            INDEX idx_categoria (categoria),
            INDEX idx_data_criacao (data_criacao),
            
            -- Índice composto para consultas frequentes
            INDEX idx_usuario_data (id_usuario, data),
            INDEX idx_usuario_tipo (id_usuario, tipo)
            
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql);
        
        echo "<div class='alert alert-success'>";
        echo "<h4>✅ Tabela 'transacoes' criada com sucesso!</h4>";
        echo "<p>A tabela foi criada com a estrutura otimizada para importação de extratos.</p>";
        echo "</div>";
        
        // Verificar estrutura criada
        verificarEstruturaCriada();
        
        // Testar inserção
        testarInsercao();
        
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>";
        echo "<h4>❌ Erro ao criar tabela:</h4>";
        echo "<p><strong>Mensagem:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Código:</strong> " . $e->getCode() . "</p>";
        echo "<p><strong>SQL State:</strong> " . $e->errorInfo[0] . "</p>";
        echo "</div>";
    }
}

function verificarEstruturaCriada() {
    global $pdo;
    
    echo "<h3>📋 Estrutura da Tabela Criada:</h3>";
    
    try {
        $stmt = $pdo->query("DESCRIBE transacoes");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped table-hover'>";
        echo "<thead class='table-dark'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        echo "</thead>";
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
}

function testarInsercao() {
    global $pdo, $userId;
    
    echo "<h3>🧪 Teste de Inserção:</h3>";
    
    try {
        // Remover transações de teste anteriores
        $stmt = $pdo->prepare("DELETE FROM transacoes WHERE id_usuario = ? AND categoria = 'Teste Criação'");
        $stmt->execute([$userId]);
        
        // Dados de teste
        $transacoesTeste = [
            [
                'data' => '2025-01-15',
                'descricao' => 'TESTE CRIAÇÃO - PIX RECEBIDO',
                'valor' => 150.00,
                'tipo' => 'receita',
                'categoria' => 'Teste Criação'
            ],
            [
                'data' => '2025-01-14',
                'descricao' => 'TESTE CRIAÇÃO - COMPRA CARTÃO',
                'valor' => -85.50,
                'tipo' => 'despesa',
                'categoria' => 'Teste Criação'
            ]
        ];
        
        $sucessos = 0;
        $erros = 0;
        
        foreach ($transacoesTeste as $i => $transacao) {
            try {
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
                    $transacao['data'],
                    $transacao['descricao'],
                    $transacao['valor'],
                    $transacao['tipo'],
                    $transacao['categoria']
                ]);
                
                if ($resultado) {
                    $idInserido = $pdo->lastInsertId();
                    echo "<div class='alert alert-success'>";
                    echo "<h5>✅ Transação " . ($i + 1) . " inserida com sucesso!</h5>";
                    echo "<p><strong>ID:</strong> $idInserido</p>";
                    echo "<p><strong>Descrição:</strong> " . $transacao['descricao'] . "</p>";
                    echo "<p><strong>Valor:</strong> R$ " . number_format($transacao['valor'], 2, ',', '.') . "</p>";
                    echo "</div>";
                    $sucessos++;
                } else {
                    echo "<div class='alert alert-danger'>";
                    echo "<h5>❌ Falha na inserção da transação " . ($i + 1) . "</h5>";
                    echo "</div>";
                    $erros++;
                }
                
            } catch (PDOException $e) {
                echo "<div class='alert alert-danger'>";
                echo "<h5>❌ Erro na transação " . ($i + 1) . ":</h5>";
                echo "<p><strong>Mensagem:</strong> " . $e->getMessage() . "</p>";
                echo "<p><strong>Código:</strong> " . $e->getCode() . "</p>";
                echo "</div>";
                $erros++;
            }
        }
        
        // Resumo do teste
        echo "<div class='alert alert-info'>";
        echo "<h5>📊 Resumo do Teste:</h5>";
        echo "<ul>";
        echo "<li><strong>Sucessos:</strong> $sucessos</li>";
        echo "<li><strong>Erros:</strong> $erros</li>";
        echo "<li><strong>Total:</strong> " . count($transacoesTeste) . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // Verificar transações inseridas
        if ($sucessos > 0) {
            echo "<h4>📋 Transações Inseridas:</h4>";
            
            $stmt = $pdo->prepare("
                SELECT id, data, descricao, valor, tipo, categoria, data_criacao
                FROM transacoes 
                WHERE id_usuario = ? AND categoria = 'Teste Criação'
                ORDER BY data_criacao DESC
            ");
            $stmt->execute([$userId]);
            $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($transacoes)) {
                echo "<div class='table-responsive'>";
                echo "<table class='table table-striped'>";
                echo "<thead><tr><th>ID</th><th>Data</th><th>Descrição</th><th>Valor</th><th>Tipo</th><th>Criado em</th></tr></thead>";
                echo "<tbody>";
                
                foreach ($transacoes as $transacao) {
                    $cor = $transacao['valor'] > 0 ? 'text-success' : 'text-danger';
                    echo "<tr>";
                    echo "<td>" . $transacao['id'] . "</td>";
                    echo "<td>" . $transacao['data'] . "</td>";
                    echo "<td>" . htmlspecialchars($transacao['descricao']) . "</td>";
                    echo "<td class='$cor'>R$ " . number_format($transacao['valor'], 2, ',', '.') . "</td>";
                    echo "<td><span class='badge bg-" . ($transacao['tipo'] === 'receita' ? 'success' : 'danger') . "'>" . ucfirst($transacao['tipo']) . "</span></td>";
                    echo "<td>" . date('d/m/Y H:i', strtotime($transacao['data_criacao'])) . "</td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
                echo "</div>";
            }
            
            // Limpar transações de teste
            $stmt = $pdo->prepare("DELETE FROM transacoes WHERE id_usuario = ? AND categoria = 'Teste Criação'");
            $stmt->execute([$userId]);
            echo "<p>🗑️ <strong>Transações de teste removidas</strong></p>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>";
        echo "<h5>❌ Erro no teste de inserção:</h5>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Verificar índices criados
echo "<h3>📊 Índices da Tabela:</h3>";

try {
    $stmt = $pdo->query("SHOW INDEX FROM transacoes");
    $indices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($indices)) {
        echo "<p>Nenhum índice encontrado.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>Nome</th><th>Coluna</th><th>Único</th><th>Tipo</th><th>Comentário</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($indices as $indice) {
            echo "<tr>";
            echo "<td><strong>" . $indice['Key_name'] . "</strong></td>";
            echo "<td>" . $indice['Column_name'] . "</td>";
            echo "<td>" . ($indice['Non_unique'] ? 'Não' : 'Sim') . "</td>";
            echo "<td>" . $indice['Index_type'] . "</td>";
            echo "<td>" . ($indice['Comment'] ?? '-') . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao verificar índices:</strong> " . $e->getMessage() . "</p>";
}

// Estatísticas finais
echo "<h3>📈 Estatísticas da Tabela:</h3>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transacoes");
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as usuario FROM transacoes WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $usuario = $stmt->fetchColumn();
    
    echo "<div class='row'>";
    echo "<div class='col-md-4'>";
    echo "<div class='card bg-primary text-white'>";
    echo "<div class='card-body text-center'>";
    echo "<h5 class='card-title'>Total de Transações</h5>";
    echo "<h2>$total</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='col-md-4'>";
    echo "<div class='card bg-success text-white'>";
    echo "<div class='card-body text-center'>";
    echo "<h5 class='card-title'>Suas Transações</h5>";
    echo "<h2>$usuario</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='col-md-4'>";
    echo "<div class='card bg-info text-white'>";
    echo "<div class='card-body text-center'>";
    echo "<h5 class='card-title'>Status</h5>";
    echo "<h2>✅ OK</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao obter estatísticas:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<div class='alert alert-success'>";
echo "<h4>🎉 Tabela 'transacoes' Pronta!</h4>";
echo "<p>A tabela foi criada com sucesso e está pronta para importação de extratos CSV e PDF.</p>";
echo "</div>";

echo "<p><a href='importar_extrato_csv.php' class='btn btn-success'>← Testar Importação CSV</a></p>";
echo "<p><a href='importar_extrato_pdf.php' class='btn btn-primary'>← Testar Importação PDF</a></p>";
echo "<p><a href='extrato_completo.php' class='btn btn-secondary'>← Ver Extrato</a></p>";
?>
