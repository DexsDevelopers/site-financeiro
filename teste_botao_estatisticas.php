<?php
// teste_botao_estatisticas.php - Teste para verificar o botão de estatísticas

echo "<h1>🧪 TESTE DO BOTÃO DE ESTATÍSTICAS</h1>";
echo "<hr>";

// 1. Verificar se o arquivo tarefas.php foi modificado
echo "<h2>1. Verificação do Arquivo Principal</h2>";
$tarefas_file = 'tarefas.php';
if (file_exists($tarefas_file)) {
    echo "✅ <strong>{$tarefas_file}</strong> - Arquivo existe<br>";
    $tarefas_content = file_get_contents($tarefas_file);
} else {
    echo "❌ <strong>{$tarefas_file}</strong> - Arquivo NÃO existe<br>";
    $tarefas_content = '';
}

echo "<br><h2>2. Verificação do Botão de Estatísticas</h2>";

// Verificar se o botão foi adicionado
if (strpos($tarefas_content, 'mostrarEstatisticas()') !== false) {
    echo "✅ <strong>Botão de Estatísticas</strong> - Implementado<br>";
} else {
    echo "❌ <strong>Botão de Estatísticas</strong> - NÃO encontrado<br>";
}

if (strpos($tarefas_content, 'modalEstatisticas') !== false) {
    echo "✅ <strong>Modal de Estatísticas</strong> - Implementado<br>";
} else {
    echo "❌ <strong>Modal de Estatísticas</strong> - NÃO encontrado<br>";
}

echo "<br><h2>3. Verificação dos Arquivos de Suporte</h2>";

// Verificar arquivos PHP de suporte
$arquivos_suporte = [
    'buscar_tarefas_hoje.php' => 'Buscar tarefas de hoje',
    'buscar_distribuicao_prioridade.php' => 'Buscar distribuição por prioridade',
    'buscar_produtividade_7_dias.php' => 'Buscar produtividade 7 dias'
];

foreach ($arquivos_suporte as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "✅ <strong>{$descricao}</strong> - Arquivo {$arquivo} existe<br>";
    } else {
        echo "❌ <strong>{$descricao}</strong> - Arquivo {$arquivo} NÃO existe<br>";
    }
}

echo "<br><h2>4. Verificação das Funcionalidades JavaScript</h2>";

// Verificar funções JavaScript
$funcoes_js = [
    'mostrarEstatisticas' => 'Função para mostrar modal',
    'carregarEstatisticas' => 'Função para carregar dados',
    'carregarTarefasHoje' => 'Função para carregar tarefas de hoje',
    'carregarDistribuicaoPrioridade' => 'Função para distribuição por prioridade',
    'carregarGraficoProdutividade' => 'Função para gráfico de produtividade'
];

foreach ($funcoes_js as $funcao => $descricao) {
    if (strpos($tarefas_content, "function {$funcao}") !== false) {
        echo "✅ <strong>{$descricao}</strong> - Função '{$funcao}' implementada<br>";
    } else {
        echo "❌ <strong>{$descricao}</strong> - Função '{$funcao}' NÃO encontrada<br>";
    }
}

echo "<br><h2>5. Verificação dos Estilos CSS</h2>";

// Verificar estilos CSS
$estilos_css = [
    'stat-card-small' => 'Cards pequenos de estatística',
    'priority-card' => 'Cards de prioridade',
    'task-item-small' => 'Itens pequenos de tarefa',
    'productivity-chart' => 'Container do gráfico'
];

foreach ($estilos_css as $classe => $descricao) {
    if (strpos($tarefas_content, $classe) !== false) {
        echo "✅ <strong>{$descricao}</strong> - Classe '{$classe}' implementada<br>";
    } else {
        echo "❌ <strong>{$descricao}</strong> - Classe '{$classe}' NÃO encontrada<br>";
    }
}

echo "<br><h2>6. Verificação do Chart.js</h2>";

// Verificar se Chart.js foi incluído
if (strpos($tarefas_content, 'chart.js') !== false) {
    echo "✅ <strong>Chart.js</strong> - Biblioteca incluída<br>";
} else {
    echo "❌ <strong>Chart.js</strong> - Biblioteca NÃO incluída<br>";
}

echo "<br><h2>7. Instruções para Teste Manual</h2>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🚀 Como testar o botão de estatísticas:</h4>";
echo "<ol>";
echo "<li><strong>Acesse a página de tarefas:</strong> <a href='tarefas.php' target='_blank'>tarefas.php</a></li>";
echo "<li><strong>Localize o botão:</strong> Procure pelo botão 'Estatísticas' ao lado do botão 'Nova Tarefa'</li>";
echo "<li><strong>Clique no botão:</strong> Deve abrir um modal com estatísticas detalhadas</li>";
echo "<li><strong>Verifique as seções:</strong></li>";
echo "    <ul>";
echo "        <li>Resumo Geral (cards com números)</li>";
echo "        <li>Tarefas de Hoje (lista de tarefas)</li>";
echo "        <li>Distribuição por Prioridade (gráficos de barras)</li>";
echo "        <li>Gráfico de Produtividade (gráfico de linha dos últimos 7 dias)</li>";
echo "    </ul>";
echo "<li><strong>Teste a responsividade:</strong> Redimensione a janela para ver a adaptação</li>";
echo "<li><strong>Teste o botão Exportar:</strong> Deve mostrar uma mensagem de 'Em Desenvolvimento'</li>";
echo "</ol>";
echo "</div>";

echo "<br><h2>8. Funcionalidades Implementadas</h2>";
echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✨ Recursos do Botão de Estatísticas:</h4>";
echo "<ul>";
echo "<li><strong>📊 Resumo Geral:</strong> Cards com estatísticas principais (concluídas, pendentes, semana, tempo)</li>";
echo "<li><strong>📋 Tarefas de Hoje:</strong> Lista completa das tarefas do dia com status e prioridade</li>";
echo "<li><strong>📈 Distribuição por Prioridade:</strong> Gráficos de barras mostrando distribuição Alta/Média/Baixa</li>";
echo "<li><strong>📊 Gráfico de Produtividade:</strong> Gráfico de linha dos últimos 7 dias</li>";
echo "<li><strong>🎨 Design Moderno:</strong> Modal responsivo com design consistente</li>";
echo "<li><strong>⚡ Carregamento Dinâmico:</strong> Dados carregados via AJAX em tempo real</li>";
echo "<li><strong>📱 Responsivo:</strong> Adapta-se perfeitamente a mobile e desktop</li>";
echo "<li><strong>🔒 Seguro:</strong> Verificação de sessão e permissões</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>⚠️ Observações Importantes:</h4>";
echo "<ul>";
echo "<li>O modal só carrega dados quando é aberto (otimização de performance)</li>";
echo "<li>Os dados são buscados em tempo real do banco de dados</li>";
echo "<li>O gráfico de produtividade mostra os últimos 7 dias</li>";
echo "<li>As tarefas de hoje incluem tanto pendentes quanto concluídas</li>";
echo "<li>A distribuição por prioridade considera apenas tarefas pendentes</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✅ Teste do Botão de Estatísticas concluído!</strong> Acesse <a href='tarefas.php'>tarefas.php</a> para testar a funcionalidade.</p>";
?>
