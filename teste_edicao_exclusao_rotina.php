<?php
// teste_edicao_exclusao_rotina.php - Teste das funcionalidades de editar e excluir hábitos

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🧪 TESTE DAS FUNCIONALIDADES DE EDIÇÃO E EXCLUSÃO DE HÁBITOS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    echo "<a href='index.php' class='btn btn-primary'>Fazer Login</a><br><br>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br><br>";

// Verificar se os arquivos necessários existem
echo "<h3>🔍 VERIFICAÇÃO DOS ARQUIVOS</h3>";

$arquivos_necessarios = [
    'editar_rotina_diaria.php' => 'Arquivo para editar hábitos',
    'excluir_rotina_diaria.php' => 'Arquivo para excluir hábitos',
    'tarefas.php' => 'Página principal com rotina diária'
];

foreach ($arquivos_necessarios as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "✅ $arquivo - $descricao<br>";
    } else {
        echo "❌ $arquivo - $descricao (NÃO ENCONTRADO)<br>";
    }
}

echo "<hr>";

// Verificar estrutura da tabela rotina_diaria
echo "<h3>📋 VERIFICAÇÃO DA ESTRUTURA DA TABELA</h3>";

try {
    $stmt = $pdo->query("DESCRIBE rotina_diaria");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Tabela 'rotina_diaria' existe<br>";
    echo "📋 Colunas encontradas: ";
    $nomes_colunas = array_column($colunas, 'Field');
    echo implode(', ', $nomes_colunas) . "<br>";
    
    // Verificar se as colunas necessárias existem
    $colunas_necessarias = ['id', 'id_usuario', 'nome', 'horario', 'status', 'data_execucao'];
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

// Verificar hábitos existentes
echo "<h3>📊 VERIFICAÇÃO DOS HÁBITOS EXISTENTES</h3>";

try {
    $userId = $_SESSION['user_id'];
    $dataHoje = date('Y-m-d');
    
    // Contar hábitos de hoje
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rotina_diaria WHERE id_usuario = ? AND data_execucao = ?");
    $stmt->execute([$userId, $dataHoje]);
    $total = $stmt->fetch()['total'];
    echo "📊 Total de hábitos para hoje: $total<br>";
    
    if ($total > 0) {
        // Mostrar alguns hábitos
        $stmt = $pdo->prepare("SELECT id, nome, horario, status FROM rotina_diaria WHERE id_usuario = ? AND data_execucao = ? ORDER BY ordem LIMIT 5");
        $stmt->execute([$userId, $dataHoje]);
        $habitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📋 Hábitos encontrados:<br>";
        foreach ($habitos as $habito) {
            echo "&nbsp;&nbsp;- ID: {$habito['id']}, Nome: {$habito['nome']}, Horário: " . ($habito['horario'] ?: 'Não definido') . ", Status: {$habito['status']}<br>";
        }
    } else {
        echo "⚠️ Nenhum hábito encontrado para hoje<br>";
        echo "💡 <a href='tarefas.php'>Acesse a página de tarefas</a> para criar hábitos<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro ao verificar hábitos: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Verificar funcionalidades JavaScript
echo "<h3>🔧 VERIFICAÇÃO DAS FUNCIONALIDADES JAVASCRIPT</h3>";

$conteudo_tarefas = file_get_contents('tarefas.php');

$funcoes_javascript = [
    'editarRotina(' => 'Função para editar hábito',
    'excluirRotina(' => 'Função para excluir hábito',
    'salvarEdicaoHabit()' => 'Função para salvar edição',
    'confirmarExclusaoHabit()' => 'Função para confirmar exclusão'
];

foreach ($funcoes_javascript as $funcao => $descricao) {
    if (strpos($conteudo_tarefas, $funcao) !== false) {
        echo "✅ $descricao<br>";
    } else {
        echo "❌ $descricao (NÃO ENCONTRADA)<br>";
    }
}

echo "<hr>";

// Verificar modais
echo "<h3>🎭 VERIFICAÇÃO DOS MODAIS</h3>";

$modais = [
    'modalEditarHabit' => 'Modal para editar hábito',
    'modalExcluirHabit' => 'Modal para excluir hábito'
];

foreach ($modais as $modal => $descricao) {
    if (strpos($conteudo_tarefas, $modal) !== false) {
        echo "✅ $descricao<br>";
    } else {
        echo "❌ $descricao (NÃO ENCONTRADO)<br>";
    }
}

echo "<hr>";

// Verificar botões de ação
echo "<h3>🔘 VERIFICAÇÃO DOS BOTÕES DE AÇÃO</h3>";

$botoes = [
    'onclick="editarRotina(' => 'Botão de editar',
    'onclick="excluirRotina(' => 'Botão de excluir',
    'habit-actions' => 'Container dos botões de ação'
];

foreach ($botoes as $botao => $descricao) {
    if (strpos($conteudo_tarefas, $botao) !== false) {
        echo "✅ $descricao<br>";
    } else {
        echo "❌ $descricao (NÃO ENCONTRADO)<br>";
    }
}

echo "<hr>";

// Resumo final
echo "<h2>📊 RESUMO FINAL</h2>";

$total_verificacoes = count($arquivos_necessarios) + count($funcoes_javascript) + count($modais) + count($botoes);
$verificacoes_ok = 0;

// Contar verificações OK
foreach ($arquivos_necessarios as $arquivo => $descricao) {
    if (file_exists($arquivo)) $verificacoes_ok++;
}

foreach ($funcoes_javascript as $funcao => $descricao) {
    if (strpos($conteudo_tarefas, $funcao) !== false) $verificacoes_ok++;
}

foreach ($modais as $modal => $descricao) {
    if (strpos($conteudo_tarefas, $modal) !== false) $verificacoes_ok++;
}

foreach ($botoes as $botao => $descricao) {
    if (strpos($conteudo_tarefas, $botao) !== false) $verificacoes_ok++;
}

echo "<p><strong>Verificações OK: $verificacoes_ok/$total_verificacoes</strong></p>";

if ($verificacoes_ok == $total_verificacoes) {
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Todas as funcionalidades estão implementadas!</h4>";
    echo "<p>As funcionalidades de editar e excluir hábitos estão prontas para uso.</p>";
    echo "<ol>";
    echo "<li><a href='tarefas.php'>Acesse a página de tarefas</a></li>";
    echo "<li>Passe o mouse sobre um hábito para ver os botões de ação</li>";
    echo "<li>Clique no botão de editar (lápis) para modificar um hábito</li>";
    echo "<li>Clique no botão de excluir (lixeira) para remover um hábito</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>❌ Algumas funcionalidades ainda precisam ser implementadas</h4>";
    echo "<p>Verifique os itens marcados com ❌ acima e corrija os problemas.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>✅ Teste concluído!</strong> Use as recomendações acima para resolver os problemas.</p>";
?>
