<?php
// teste_importacao.php - Teste de importação de transações

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🧪 Teste de Importação de Transações</h2>";

// Simular transações de teste
$transacoesTeste = [
    [
        'data' => date('Y-m-d'),
        'descricao' => 'TESTE PIX RECEBIDO - JOÃO SILVA',
        'valor' => 150.00,
        'tipo' => 'receita'
    ],
    [
        'data' => date('Y-m-d', strtotime('-1 day')),
        'descricao' => 'TESTE COMPRA CARTÃO - SUPERMERCADO',
        'valor' => -85.50,
        'tipo' => 'despesa'
    ],
    [
        'data' => date('Y-m-d', strtotime('-2 days')),
        'descricao' => 'TESTE TRANSFERÊNCIA - PAGAMENTO',
        'valor' => -200.00,
        'tipo' => 'despesa'
    ]
];

echo "<h3>📊 Transações de Teste:</h3>";
echo "<div class='table-responsive'>";
echo "<table class='table table-striped'>";
echo "<thead><tr><th>Data</th><th>Descrição</th><th>Valor</th><th>Tipo</th></tr></thead>";
echo "<tbody>";

foreach ($transacoesTeste as $transacao) {
    $cor = $transacao['valor'] > 0 ? 'text-success' : 'text-danger';
    echo "<tr>";
    echo "<td>" . $transacao['data'] . "</td>";
    echo "<td>" . htmlspecialchars($transacao['descricao']) . "</td>";
    echo "<td class='$cor'>R$ " . number_format($transacao['valor'], 2, ',', '.') . "</td>";
    echo "<td><span class='badge bg-" . ($transacao['tipo'] === 'receita' ? 'success' : 'danger') . "'>" . ucfirst($transacao['tipo']) . "</span></td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";
echo "</div>";

// Testar importação
echo "<h3>🔧 Testando Importação:</h3>";

try {
    $salvas = 0;
    $duplicadas = 0;
    $erros = 0;
    
    foreach ($transacoesTeste as $transacao) {
        try {
            // Verificar se já existe
            $stmt = $pdo->prepare("
                SELECT id FROM transacoes 
                WHERE id_usuario = ? 
                AND data = ? 
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
                echo "<p>⚠️ Transação duplicada: " . $transacao['descricao'] . "</p>";
                continue;
            }
            
            // Inserir transação
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
            
            $stmt->execute([
                $userId,
                $transacao['data'],
                $transacao['descricao'],
                $transacao['valor'],
                $transacao['tipo'],
                'Teste Importação PDF'
            ]);
            
            $salvas++;
            echo "<p>✅ Transação salva: " . $transacao['descricao'] . "</p>";
            
        } catch (PDOException $e) {
            $erros++;
            echo "<p>❌ Erro ao salvar: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<div class='alert alert-success'>";
    echo "<h5>📊 Resultado da Importação:</h5>";
    echo "<ul>";
    echo "<li><strong>Salvas:</strong> $salvas</li>";
    echo "<li><strong>Duplicadas:</strong> $duplicadas</li>";
    echo "<li><strong>Erros:</strong> $erros</li>";
    echo "<li><strong>Total:</strong> " . count($transacoesTeste) . "</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>❌ Erro na Importação:</h5>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// Verificar transações do usuário
echo "<h3>📋 Transações do Usuário (Últimas 10):</h3>";

try {
    $stmt = $pdo->prepare("
        SELECT data, descricao, valor, tipo, categoria, data_criacao
        FROM transacoes 
        WHERE id_usuario = ? 
        ORDER BY data_criacao DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transacoes)) {
        echo "<p>Nenhuma transação encontrada.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>Data</th><th>Descrição</th><th>Valor</th><th>Tipo</th><th>Categoria</th><th>Criado em</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($transacoes as $transacao) {
            $cor = $transacao['valor'] > 0 ? 'text-success' : 'text-danger';
            echo "<tr>";
            echo "<td>" . $transacao['data'] . "</td>";
            echo "<td>" . htmlspecialchars($transacao['descricao']) . "</td>";
            echo "<td class='$cor'>R$ " . number_format($transacao['valor'], 2, ',', '.') . "</td>";
            echo "<td><span class='badge bg-" . ($transacao['tipo'] === 'receita' ? 'success' : 'danger') . "'>" . ucfirst($transacao['tipo']) . "</span></td>";
            echo "<td>" . htmlspecialchars($transacao['categoria']) . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($transacao['data_criacao'])) . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao buscar transações: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='importar_extrato_pdf.php'>← Voltar para Importação</a></p>";
echo "<p><a href='extrato_completo.php'>← Ver Extrato Completo</a></p>";
?>
