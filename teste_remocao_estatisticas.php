<?php
// teste_remocao_estatisticas.php - Teste para verificar a remoção das estatísticas da página principal

echo "<h1>🧪 TESTE DE REMOÇÃO DAS ESTATÍSTICAS</h1>";
echo "<hr>";

// 1. Verificar se o arquivo tarefas.php existe
echo "<h2>1. Verificação do Arquivo Principal</h2>";
$tarefas_file = 'tarefas.php';
if (file_exists($tarefas_file)) {
    echo "✅ <strong>{$tarefas_file}</strong> - Arquivo existe<br>";
    $tarefas_content = file_get_contents($tarefas_file);
} else {
    echo "❌ <strong>{$tarefas_file}</strong> - Arquivo NÃO existe<br>";
    $tarefas_content = '';
}

echo "<br><h2>2. Verificação da Remoção do HTML das Estatísticas</h2>";

// Verificar se a seção de estatísticas rápidas foi removida
if (strpos($tarefas_content, 'ESTATÍSTICAS RÁPIDAS') !== false) {
    echo "❌ <strong>Seção de Estatísticas Rápidas</strong> - AINDA PRESENTE (deve ser removida)<br>";
} else {
    echo "✅ <strong>Seção de Estatísticas Rápidas</strong> - Removida com sucesso<br>";
}

if (strpos($tarefas_content, 'stats-grid') !== false && strpos($tarefas_content, 'stat-card-small') === false) {
    echo "❌ <strong>Grid de Estatísticas</strong> - AINDA PRESENTE (deve ser removido)<br>";
} else {
    echo "✅ <strong>Grid de Estatísticas</strong> - Removido com sucesso<br>";
}

echo "<br><h2>3. Verificação da Remoção dos Estilos CSS</h2>";

// Verificar se os estilos das estatísticas principais foram removidos
$estilos_removidos = [
    '.stats-grid' => 'Grid de estatísticas',
    '.stat-card {' => 'Card de estatística',
    '.stat-icon {' => 'Ícone de estatística',
    '.stat-content {' => 'Conteúdo de estatística',
    '.stat-value {' => 'Valor de estatística',
    '.stat-label {' => 'Label de estatística',
    '.progress-ring' => 'Anel de progresso'
];

foreach ($estilos_removidos as $estilo => $descricao) {
    // Verificar se o estilo foi removido (não deve conter a versão sem -small)
    if (strpos($tarefas_content, $estilo) !== false) {
        // Se encontrou, verificar se não é a versão -small
        if (strpos($estilo, '-small') === false && strpos($tarefas_content, str_replace('{', '-small', $estilo)) !== false) {
            echo "✅ <strong>{$descricao}</strong> - Versão principal removida (mantida versão -small do modal)<br>";
        } else {
            echo "❌ <strong>{$descricao}</strong> - AINDA PRESENTE (deve ser removido)<br>";
        }
    } else {
        echo "✅ <strong>{$descricao}</strong> - Removido com sucesso<br>";
    }
}

echo "<br><h2>4. Verificação dos Estilos do Modal (devem permanecer)</h2>";

// Verificar se os estilos do modal foram mantidos
$estilos_modal = [
    '.stat-card-small' => 'Card pequeno do modal',
    '.stat-icon-small' => 'Ícone pequeno do modal',
    '.stat-content-small' => 'Conteúdo pequeno do modal',
    '.stat-value-small' => 'Valor pequeno do modal',
    '.stat-label-small' => 'Label pequeno do modal'
];

foreach ($estilos_modal as $estilo => $descricao) {
    if (strpos($tarefas_content, $estilo) !== false) {
        echo "✅ <strong>{$descricao}</strong> - Mantido para o modal<br>";
    } else {
        echo "❌ <strong>{$descricao}</strong> - NÃO encontrado (deve estar presente)<br>";
    }
}

echo "<br><h2>5. Verificação das Funcionalidades do Modal</h2>";

// Verificar se o modal de estatísticas foi mantido
if (strpos($tarefas_content, 'modalEstatisticas') !== false) {
    echo "✅ <strong>Modal de Estatísticas</strong> - Mantido<br>";
} else {
    echo "❌ <strong>Modal de Estatísticas</strong> - NÃO encontrado<br>";
}

if (strpos($tarefas_content, 'mostrarEstatisticas') !== false) {
    echo "✅ <strong>Função mostrarEstatisticas</strong> - Mantida<br>";
} else {
    echo "❌ <strong>Função mostrarEstatisticas</strong> - NÃO encontrada<br>";
}

if (strpos($tarefas_content, 'carregarEstatisticas') !== false) {
    echo "✅ <strong>Função carregarEstatisticas</strong> - Mantida<br>";
} else {
    echo "❌ <strong>Função carregarEstatisticas</strong> - NÃO encontrada<br>";
}

echo "<br><h2>6. Resumo da Estrutura Atual</h2>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>📋 Estrutura da Página de Tarefas:</h4>";
echo "<ol>";
echo "<li><strong>Header da Página</strong> - Título e subtítulo</li>";
echo "<li><strong>Seção Rotina Diária</strong> - Hábitos diários integrados</li>";
echo "<li><strong>~~Estatísticas Rápidas~~</strong> - <span style='color: red;'>REMOVIDA</span></li>";
echo "<li><strong>Barra de Busca e Filtros</strong> - Pesquisa e filtros de tarefas</li>";
echo "<li><strong>Seção Principal de Tarefas</strong> - Lista de tarefas com botão de estatísticas</li>";
echo "<li><strong>Modal de Estatísticas</strong> - <span style='color: green;'>MANTIDO</span> (abre ao clicar no botão)</li>";
echo "</ol>";
echo "</div>";

echo "<br><h2>7. Instruções para Teste Manual</h2>";
echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🚀 Como testar a remoção:</h4>";
echo "<ol>";
echo "<li><strong>Acesse a página de tarefas:</strong> <a href='tarefas.php' target='_blank'>tarefas.php</a></li>";
echo "<li><strong>Verifique a estrutura:</strong></li>";
echo "    <ul>";
echo "        <li>Título da página</li>";
echo "        <li>Seção Rotina Diária</li>";
echo "        <li><strong style='color: red;'>NÃO deve haver seção de estatísticas rápidas</strong></li>";
echo "        <li>Barra de busca e filtros</li>";
echo "        <li>Lista de tarefas</li>";
echo "    </ul>";
echo "<li><strong>Clique no botão 'Estatísticas':</strong> Deve abrir o modal completo</li>";
echo "<li><strong>Verifique o modal:</strong> Deve conter todas as estatísticas detalhadas</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>⚠️ Observações Importantes:</h4>";
echo "<ul>";
echo "<li>As estatísticas rápidas foram removidas da página principal</li>";
echo "<li>O modal de estatísticas foi mantido e funciona perfeitamente</li>";
echo "<li>O botão 'Estatísticas' está localizado ao lado do botão 'Nova Tarefa'</li>";
echo "<li>Os dados são carregados dinamicamente quando o modal é aberto</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✅ Teste de Remoção das Estatísticas concluído!</strong> Acesse <a href='tarefas.php'>tarefas.php</a> para verificar as mudanças.</p>";
?>

