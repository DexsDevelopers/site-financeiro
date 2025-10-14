<?php
// debug_importacao_csv.php - Debug específico da importação CSV

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🔍 Debug Importação CSV - Análise de Erros</h2>";

// Simular transações de teste com diferentes problemas
$transacoesTeste = [
    [
        'data' => '15/01/2025',
        'descricao' => 'PIX RECEBIDO - JOÃO SILVA',
        'valor' => 150.00,
        'tipo' => 'receita'
    ],
    [
        'data' => '14/01/2025',
        'descricao' => 'COMPRA CARTÃO - SUPERMERCADO',
        'valor' => -85.50,
        'tipo' => 'despesa'
    ],
    [
        'data' => '13/01/2025',
        'descricao' => 'TRANSFERÊNCIA - PAGAMENTO',
        'valor' => -200.00,
        'tipo' => 'despesa'
    ]
];

echo "<h3>🧪 Testando Importação de Transações:</h3>";

foreach ($transacoesTeste as $i => $transacao) {
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'>";
    echo "<h5>Transação " . ($i + 1) . "</h5>";
    echo "</div>";
    echo "<div class='card-body'>";
    
    echo "<p><strong>Dados originais:</strong></p>";
    echo "<pre class='bg-light p-2'>" . json_encode($transacao, JSON_PRETTY_PRINT) . "</pre>";
    
    // Testar cada etapa do processo
    echo "<h6>🔍 Análise de Validação:</h6>";
    
    // 1. Verificar dados obrigatórios
    $data = $transacao['data'];
    $descricao = $transacao['descricao'];
    $valor = $transacao['valor'];
    
    echo "<p><strong>Data:</strong> '$data' - " . (empty($data) ? "❌ Vazia" : "✅ OK") . "</p>";
    echo "<p><strong>Descrição:</strong> '$descricao' - " . (empty($descricao) ? "❌ Vazia" : "✅ OK") . "</p>";
    echo "<p><strong>Valor:</strong> '$valor' - " . (empty($valor) ? "❌ Vazio" : "✅ OK") . "</p>";
    
    // 2. Testar verificação de duplicatas
    echo "<h6>🔍 Teste de Duplicatas:</h6>";
    
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM transacoes 
            WHERE id_usuario = ? 
            AND data_transacao = ? 
            AND valor = ? 
            AND descricao LIKE ?
        ");
        $stmt->execute([
            $userId,
            $data,
            $valor,
            '%' . substr($descricao, 0, 20) . '%'
        ]);
        
        $duplicata = $stmt->fetch();
        if ($duplicata) {
            echo "<p>⚠️ <strong>Duplicata encontrada:</strong> ID " . $duplicata['id'] . "</p>";
        } else {
            echo "<p>✅ <strong>Não é duplicata</strong></p>";
        }
        
    } catch (PDOException $e) {
        echo "<p>❌ <strong>Erro na verificação de duplicatas:</strong> " . $e->getMessage() . "</p>";
    }
    
    // 3. Testar inserção
    echo "<h6>🔍 Teste de Inserção:</h6>";
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO transacoes (
                id_usuario, 
                data_transacao, 
                descricao, 
                valor, 
                tipo, 
                data_criacao
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $resultado = $stmt->execute([
            $userId,
            $data,
            $descricao,
            $valor,
            $transacao['tipo']
        ]);
        
        if ($resultado) {
            $idInserido = $pdo->lastInsertId();
            echo "<p>✅ <strong>Inserção bem-sucedida:</strong> ID $idInserido</p>";
            
            // Remover a transação de teste
            $stmtDelete = $pdo->prepare("DELETE FROM transacoes WHERE id = ?");
            $stmtDelete->execute([$idInserido]);
            echo "<p>🗑️ <strong>Transação de teste removida</strong></p>";
            
        } else {
            echo "<p>❌ <strong>Falha na inserção</strong></p>";
        }
        
    } catch (PDOException $e) {
        echo "<p>❌ <strong>Erro na inserção:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Código do erro:</strong> " . $e->getCode() . "</p>";
        echo "<p><strong>SQL State:</strong> " . $e->errorInfo[0] . "</p>";
    }
    
    echo "</div>";
    echo "</div>";
}

// Testar estrutura da tabela
echo "<h3>🗄️ Verificação da Estrutura da Tabela:</h3>";

try {
    $stmt = $pdo->query("DESCRIBE transacoes");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='table-responsive'>";
    echo "<table class='table table-striped'>";
    echo "<thead><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($colunas as $coluna) {
        echo "<tr>";
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

// Testar endpoint de importação
echo "<h3>🔗 Teste do Endpoint de Importação:</h3>";

echo "<form id='testeImportacao' method='POST'>";
echo "<input type='hidden' name='transacoes' value='" . json_encode($transacoesTeste) . "'>";
echo "<button type='submit' class='btn btn-primary'>Testar Endpoint importar_transacoes_pdf.php</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transacoes'])) {
    echo "<h4>📊 Resultado do Teste:</h4>";
    
    $transacoes = json_decode($_POST['transacoes'], true);
    
    try {
        $salvas = 0;
        $duplicadas = 0;
        $erros = 0;
        $detalhesErros = [];
        
        foreach ($transacoes as $transacao) {
            try {
                // Verificar duplicata
                $stmt = $pdo->prepare("
                    SELECT id FROM transacoes 
                    WHERE id_usuario = ? 
                    AND data_transacao = ? 
                    AND valor = ? 
                    AND descricao LIKE ?
                ");
                $stmt->execute([
                    $userId,
                    $transacao['data'],
                    $transacao['valor'],
                    '%' . substr($transacao['descricao'], 0, 20) . '%'
                ]);
                
                if ($stmt->fetch()) {
                    $duplicadas++;
                    continue;
                }
                
                // Inserir transação
                $stmt = $pdo->prepare("
                    INSERT INTO transacoes (
                        id_usuario, 
                        data_transacao, 
                        descricao, 
                        valor, 
                        tipo, 
                        data_criacao
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $userId,
                    $transacao['data'],
                    $transacao['descricao'],
                    $transacao['valor'],
                    $transacao['tipo']
                ]);
                
                $salvas++;
                
            } catch (PDOException $e) {
                $erros++;
                $detalhesErros[] = [
                    'transacao' => $transacao,
                    'erro' => $e->getMessage(),
                    'codigo' => $e->getCode()
                ];
            }
        }
        
        echo "<div class='alert alert-info'>";
        echo "<h5>📊 Resultado:</h5>";
        echo "<ul>";
        echo "<li><strong>Salvas:</strong> $salvas</li>";
        echo "<li><strong>Duplicadas:</strong> $duplicadas</li>";
        echo "<li><strong>Erros:</strong> $erros</li>";
        echo "</ul>";
        echo "</div>";
        
        if (!empty($detalhesErros)) {
            echo "<h5>❌ Detalhes dos Erros:</h5>";
            foreach ($detalhesErros as $erro) {
                echo "<div class='alert alert-danger'>";
                echo "<p><strong>Transação:</strong> " . json_encode($erro['transacao']) . "</p>";
                echo "<p><strong>Erro:</strong> " . $erro['erro'] . "</p>";
                echo "<p><strong>Código:</strong> " . $erro['codigo'] . "</p>";
                echo "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>";
        echo "<h5>❌ Erro Geral:</h5>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Verificar transações existentes
echo "<h3>📋 Transações Existentes (Últimas 5):</h3>";

try {
    $stmt = $pdo->prepare("
        SELECT id, data_transacao, descricao, valor, tipo, data_criacao
        FROM transacoes 
        WHERE id_usuario = ? 
        ORDER BY data_criacao DESC 
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $transacoesExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transacoesExistentes)) {
        echo "<p>Nenhuma transação encontrada.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>ID</th><th>Data</th><th>Descrição</th><th>Valor</th><th>Tipo</th><th>Criado em</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($transacoesExistentes as $transacao) {
            $cor = $transacao['valor'] > 0 ? 'text-success' : 'text-danger';
            echo "<tr>";
            echo "<td>" . $transacao['id'] . "</td>";
            echo "<td>" . $transacao['data_transacao'] . "</td>";
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
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao buscar transações:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='importar_extrato_csv.php'>← Voltar para Importação CSV</a></p>";
echo "<p><a href='debug_csv.php'>← Debug CSV Completo</a></p>";
?>
