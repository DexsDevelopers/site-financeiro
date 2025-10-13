<?php
// teste_final_estatisticas_corrigidas.php - Teste final das estatísticas corrigidas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🎯 TESTE FINAL DAS ESTATÍSTICAS CORRIGIDAS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Função para testar API
function testarAPI($nome, $arquivo) {
    echo "<h3>🔍 Teste: $nome</h3>";
    echo "Arquivo: $arquivo<br>";
    
    if (!file_exists($arquivo)) {
        echo "❌ Arquivo não encontrado<br>";
        return false;
    }
    
    echo "✅ Arquivo existe<br>";
    
    // Capturar output
    ob_start();
    
    try {
        include $arquivo;
        $output = ob_get_clean();
        
        if (!empty($output)) {
            echo "✅ Output capturado (tamanho: " . strlen($output) . " bytes)<br>";
            
            // Verificar se é JSON válido
            $data = json_decode($output, true);
            if ($data !== null) {
                echo "✅ JSON válido<br>";
                
                if (isset($data['success']) && $data['success'] === true) {
                    echo "✅ API funcionando corretamente<br>";
                    
                    // Mostrar dados específicos
                    if (isset($data['tarefas'])) {
                        echo "📊 Tarefas encontradas: " . count($data['tarefas']) . "<br>";
                    }
                    if (isset($data['total'])) {
                        echo "📊 Total: " . $data['total'] . "<br>";
                    }
                    if (isset($data['labels']) && isset($data['tarefas'])) {
                        echo "📊 Gráfico - Labels: " . implode(', ', $data['labels']) . "<br>";
                        echo "📊 Gráfico - Dados: " . implode(', ', $data['tarefas']) . "<br>";
                    }
                    if (isset($data['totalGeral'])) {
                        echo "📊 Total Geral: " . $data['totalGeral'] . "<br>";
                    }
                    if (isset($data['media_diaria'])) {
                        echo "📊 Média Diária: " . $data['media_diaria'] . "<br>";
                    }
                    
                    return true;
                } else {
                    echo "❌ API retornou erro: " . ($data['message'] ?? 'Erro desconhecido') . "<br>";
                }
            } else {
                echo "❌ JSON inválido: " . json_last_error_msg() . "<br>";
                echo "Output: " . htmlspecialchars(substr($output, 0, 500)) . "<br>";
            }
        } else {
            echo "❌ Nenhum output capturado<br>";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ Erro: " . $e->getMessage() . "<br>";
    }
    
    return false;
}

// 1. Verificar estrutura da tabela
echo "<h3>1. Verificando Estrutura da Tabela</h3>";

try {
    $stmt = $pdo->query("DESCRIBE tarefas");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Tabela 'tarefas' existe<br>";
    echo "📋 Colunas encontradas: ";
    $nomes_colunas = array_column($colunas, 'Field');
    echo implode(', ', $nomes_colunas) . "<br>";
    
    // Verificar se as colunas necessárias existem
    $colunas_necessarias = ['id', 'descricao', 'prioridade', 'status', 'data_limite', 'data_criacao'];
    $colunas_faltando = array_diff($colunas_necessarias, $nomes_colunas);
    
    if (empty($colunas_faltando)) {
        echo "✅ Todas as colunas necessárias existem<br>";
    } else {
        echo "❌ Colunas faltando: " . implode(', ', $colunas_faltando) . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar tabela: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 2. Verificar dados na tabela
echo "<h3>2. Verificando Dados na Tabela</h3>";

try {
    $userId = $_SESSION['user_id'];
    
    // Contar total de tarefas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
    $stmt->execute([$userId]);
    $total = $stmt->fetch()['total'];
    echo "📊 Total de tarefas: $total<br>";
    
    if ($total > 0) {
        // Verificar tarefas de hoje
        $dataHoje = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            AND (
                DATE(data_limite) = ? 
                OR DATE(data_criacao) = ?
            )
        ");
        $stmt->execute([$userId, $dataHoje, $dataHoje]);
        $hoje = $stmt->fetch()['total'];
        echo "📅 Tarefas de hoje: $hoje<br>";
        
        // Verificar distribuição por prioridade
        $stmt = $pdo->prepare("
            SELECT prioridade, COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            GROUP BY prioridade
        ");
        $stmt->execute([$userId]);
        $prioridades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📊 Distribuição por prioridade:<br>";
        foreach ($prioridades as $p) {
            echo "&nbsp;&nbsp;- " . $p['prioridade'] . ": " . $p['total'] . "<br>";
        }
        
        // Verificar tarefas concluídas nos últimos 7 dias
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            AND status = 'concluida'
            AND data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$userId]);
        $concluidas_7_dias = $stmt->fetch()['total'];
        echo "✅ Tarefas concluídas (7 dias): $concluidas_7_dias<br>";
        
    } else {
        echo "⚠️ Nenhuma tarefa encontrada. Criando dados de teste...<br>";
        
        // Criar algumas tarefas de teste
        $tarefas_teste = [
            ['descricao' => 'Tarefa de teste 1', 'prioridade' => 'Alta', 'status' => 'pendente'],
            ['descricao' => 'Tarefa de teste 2', 'prioridade' => 'Média', 'status' => 'concluida'],
            ['descricao' => 'Tarefa de teste 3', 'prioridade' => 'Baixa', 'status' => 'pendente']
        ];
        
        foreach ($tarefas_teste as $tarefa) {
            $stmt = $pdo->prepare("
                INSERT INTO tarefas (id_usuario, descricao, prioridade, status, data_criacao, data_limite) 
                VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))
            ");
            $stmt->execute([$userId, $tarefa['descricao'], $tarefa['prioridade'], $tarefa['status']]);
        }
        
        echo "✅ Dados de teste criados<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar dados: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 3. Testar APIs
echo "<h3>3. Testando APIs</h3>";

$apis_teste = [
    'api_tarefas_hoje.php' => 'Tarefas de Hoje',
    'api_distribuicao_prioridade.php' => 'Distribuição por Prioridade',
    'api_produtividade_7_dias.php' => 'Produtividade 7 Dias'
];

$resultados = [];

foreach ($apis_teste as $arquivo => $nome) {
    $resultados[$arquivo] = testarAPI($nome, $arquivo);
    echo "<hr>";
}

// 4. Resumo dos resultados
echo "<h2>📋 RESUMO DOS RESULTADOS</h2>";

$sucessos = 0;
foreach ($resultados as $arquivo => $sucesso) {
    if ($sucesso) {
        echo "✅ $arquivo: FUNCIONANDO<br>";
        $sucessos++;
    } else {
        echo "❌ $arquivo: COM PROBLEMAS<br>";
    }
}

echo "<br><strong>APIs funcionando: $sucessos/" . count($resultados) . "</strong><br>";

// 5. Recomendações
echo "<h3>💡 RECOMENDAÇÕES</h3>";

if ($sucessos == count($resultados)) {
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Todas as APIs estão funcionando!</h4>";
    echo "<p>As estatísticas devem estar funcionando corretamente agora.</p>";
    echo "<ol>";
    echo "<li><a href='tarefas.php'>Testar o modal de estatísticas</a></li>";
    echo "<li>Verificar se os gráficos estão sendo exibidos</li>";
    echo "<li>Testar todas as funcionalidades do modal</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>❌ Algumas APIs ainda têm problemas</h4>";
    echo "<p>Verifique os erros específicos acima e execute este script novamente.</p>";
    echo "<ol>";
    echo "<li>Verifique quais APIs específicas estão com problemas</li>";
    echo "<li>Execute este script novamente</li>";
    echo "<li>Teste no modal de estatísticas</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>✅ Teste final concluído!</strong> Use as recomendações acima para resolver os problemas.</p>";
?>
