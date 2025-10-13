<?php
// teste_estatisticas_final.php - Teste final das estatísticas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🧪 TESTE FINAL DAS ESTATÍSTICAS</h2>";

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
                echo "Output: " . htmlspecialchars($output) . "<br>";
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

// Testar APIs
echo "<h2>📊 TESTE DAS APIs</h2>";

$apis = [
    'api_tarefas_hoje.php' => 'Tarefas de Hoje',
    'api_distribuicao_prioridade.php' => 'Distribuição por Prioridade',
    'api_produtividade_7_dias.php' => 'Produtividade 7 Dias'
];

$resultados = [];

foreach ($apis as $arquivo => $nome) {
    $resultados[$arquivo] = testarAPI($nome, $arquivo);
    echo "<hr>";
}

// Resumo dos resultados
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

// Verificações adicionais
echo "<h3>🔍 VERIFICAÇÕES ADICIONAIS</h3>";

// Verificar se as tabelas existem
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'tarefas'");
    if ($stmt->fetch()) {
        echo "✅ Tabela 'tarefas' existe<br>";
        
        // Verificar se há dados
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $total = $stmt->fetch()['total'];
        echo "📊 Total de tarefas: $total<br>";
        
        if ($total == 0) {
            echo "⚠️ Nenhuma tarefa encontrada. Isso pode causar problemas nas APIs.<br>";
        }
        
        // Verificar tarefas de hoje
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            AND (
                DATE(data_limite) = CURDATE() 
                OR DATE(data_criacao) = CURDATE()
            )
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $hoje = $stmt->fetch()['total'];
        echo "📅 Tarefas de hoje: $hoje<br>";
        
        // Verificar tarefas concluídas dos últimos 7 dias
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tarefas 
            WHERE id_usuario = ? 
            AND status = 'concluida'
            AND data_conclusao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $concluidas_7_dias = $stmt->fetch()['total'];
        echo "📊 Tarefas concluídas (7 dias): $concluidas_7_dias<br>";
        
    } else {
        echo "❌ Tabela 'tarefas' não existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar banco: " . $e->getMessage() . "<br>";
}

// Verificar configuração PHP
echo "<h3>⚙️ CONFIGURAÇÃO PHP</h3>";
echo "Versão PHP: " . phpversion() . "<br>";
echo "JSON support: " . (function_exists('json_encode') ? 'Sim' : 'Não') . "<br>";
echo "PDO support: " . (class_exists('PDO') ? 'Sim' : 'Não') . "<br>";
echo "Session support: " . (function_exists('session_start') ? 'Sim' : 'Não') . "<br>";

// Recomendações
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
} elseif ($sucessos > 0) {
    echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>⚠️ Algumas APIs estão funcionando</h4>";
    echo "<p>Algumas estatísticas podem estar funcionando, mas outras não.</p>";
    echo "<ol>";
    echo "<li>Verifique quais APIs específicas estão com problemas</li>";
    echo "<li>Execute <a href='corrigir_estatisticas_definitivo.php'>corrigir_estatisticas_definitivo.php</a></li>";
    echo "<li>Teste novamente</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>❌ Nenhuma API está funcionando</h4>";
    echo "<p>Há problemas fundamentais que precisam ser resolvidos.</p>";
    echo "<ol>";
    echo "<li>Execute <a href='corrigir_estatisticas_definitivo.php'>corrigir_estatisticas_definitivo.php</a></li>";
    echo "<li>Verifique a conexão com o banco de dados</li>";
    echo "<li>Verifique se o usuário está logado corretamente</li>";
    echo "<li>Execute <a href='diagnostico_estatisticas_completo.php'>diagnostico_estatisticas_completo.php</a></li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>✅ Teste final concluído!</strong> Use as recomendações acima para resolver os problemas.</p>";
?>
