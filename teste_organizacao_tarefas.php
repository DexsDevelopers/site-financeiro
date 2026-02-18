<?php
// teste_organizacao_tarefas.php - Teste para verificar a nova organização da página de tarefas

echo "<h1>🧪 TESTE DE ORGANIZAÇÃO DA PÁGINA DE TAREFAS</h1>";
echo "<hr>";

// 1. Verificar se o arquivo tarefas.php existe
echo "<h2>1. Verificação de Arquivo</h2>";
$tarefas_file = 'tarefas.php';
if (file_exists($tarefas_file)) {
    echo "✅ <strong>{$tarefas_file}</strong> - Arquivo existe<br>";
    $tarefas_content = file_get_contents($tarefas_file);
} else {
    echo "❌ <strong>{$tarefas_file}</strong> - Arquivo NÃO existe<br>";
    $tarefas_content = '';
}

echo "<br><h2>2. Verificação da Nova Estrutura Organizada</h2>";

// Verificar se a nova estrutura HTML foi implementada
$estrutura_checks = [
    'PÁGINA DE TAREFAS ORGANIZADA' => 'Nova estrutura principal',
    'HEADER DA PÁGINA' => 'Header organizado',
    'SEÇÃO ROTINA DIÁRIA' => 'Seção de rotina diária',
    'ESTATÍSTICAS RÁPIDAS' => 'Seção de estatísticas',
    'BARRA DE BUSCA E FILTROS' => 'Seção de busca e filtros',
    'SEÇÃO PRINCIPAL DE TAREFAS' => 'Seção principal de tarefas'
];

foreach ($estrutura_checks as $check => $description) {
    if (strpos($tarefas_content, $check) !== false) {
        echo "✅ <strong>{$description}</strong> - Implementada<br>";
    } else {
        echo "❌ <strong>{$description}</strong> - NÃO encontrada<br>";
    }
}

echo "<br><h2>3. Verificação dos Novos Estilos CSS</h2>";

// Verificar se os novos estilos CSS foram implementados
$css_checks = [
    'LAYOUT ORGANIZADO' => 'Estilos de layout',
    'SEÇÕES ORGANIZADAS' => 'Estilos de seções',
    'ROTINA DIÁRIA ORGANIZADA' => 'Estilos da rotina diária',
    'ESTATÍSTICAS ORGANIZADAS' => 'Estilos das estatísticas',
    'BUSCA E FILTROS ORGANIZADOS' => 'Estilos de busca e filtros',
    'SEÇÃO DE TAREFAS ORGANIZADA' => 'Estilos da seção de tarefas',
    'RESPONSIVIDADE ORGANIZADA' => 'Estilos responsivos'
];

foreach ($css_checks as $check => $description) {
    if (strpos($tarefas_content, $check) !== false) {
        echo "✅ <strong>{$description}</strong> - Implementados<br>";
    } else {
        echo "❌ <strong>{$description}</strong> - NÃO encontrados<br>";
    }
}

echo "<br><h2>4. Verificação de Classes CSS Específicas</h2>";

// Verificar classes CSS específicas
$classes_checks = [
    'page-header' => 'Header da página',
    'section-card' => 'Cards de seção',
    'section-header' => 'Header das seções',
    'habits-grid' => 'Grid de hábitos',
    'habit-item' => 'Itens de hábito',
    'stats-grid' => 'Grid de estatísticas',
    'stat-card' => 'Cards de estatística',
    'search-filters-section' => 'Seção de busca e filtros',
    'tasks-section' => 'Seção de tarefas'
];

foreach ($classes_checks as $class => $description) {
    if (strpos($tarefas_content, $class) !== false) {
        echo "✅ <strong>{$description}</strong> - Classe '{$class}' encontrada<br>";
    } else {
        echo "❌ <strong>{$description}</strong> - Classe '{$class}' NÃO encontrada<br>";
    }
}

echo "<br><h2>5. Verificação de Responsividade</h2>";

// Verificar media queries
$media_queries = [
    '@media (max-width: 768px)' => 'Breakpoint tablet',
    '@media (max-width: 576px)' => 'Breakpoint mobile'
];

foreach ($media_queries as $query => $description) {
    if (strpos($tarefas_content, $query) !== false) {
        echo "✅ <strong>{$description}</strong> - Implementada<br>";
    } else {
        echo "❌ <strong>{$description}</strong> - NÃO implementada<br>";
    }
}

echo "<br><h2>6. Instruções para Teste Manual</h2>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🚀 Como testar a nova organização:</h4>";
echo "<ol>";
echo "<li><strong>Acesse a Página:</strong> <a href='tarefas.php' target='_blank'>tarefas.php</a></li>";
echo "<li><strong>Verifique o Header:</strong> Deve ter um título centralizado e subtítulo</li>";
echo "<li><strong>Verifique as Seções:</strong> Cada seção deve ter um header com título e ações</li>";
echo "<li><strong>Teste Responsividade:</strong> Redimensione a janela para ver a adaptação</li>";
echo "<li><strong>Verifique a Rotina Diária:</strong> Se houver hábitos, devem estar em grid organizado</li>";
echo "<li><strong>Verifique as Estatísticas:</strong> Devem estar em cards organizados</li>";
echo "<li><strong>Teste Busca e Filtros:</strong> Devem estar em seção própria e organizada</li>";
echo "<li><strong>Verifique as Tarefas:</strong> Devem estar em seção principal bem estruturada</li>";
echo "</ol>";
echo "</div>";

echo "<br><h2>7. Melhorias Implementadas</h2>";
echo "<div style='background: #f0f8ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✨ Organizações Realizadas:</h4>";
echo "<ul>";
echo "<li><strong>Estrutura Hierárquica:</strong> Página dividida em seções bem definidas</li>";
echo "<li><strong>Header Centralizado:</strong> Título e subtítulo da página</li>";
echo "<li><strong>Seções Organizadas:</strong> Cada funcionalidade em sua própria seção</li>";
echo "<li><strong>Layout Responsivo:</strong> Adaptação para mobile, tablet e desktop</li>";
echo "<li><strong>Visual Moderno:</strong> Cards, grids e espaçamentos consistentes</li>";
echo "<li><strong>Navegação Intuitiva:</strong> Busca e filtros em seção dedicada</li>";
echo "<li><strong>Hierarquia Visual:</strong> Headers, badges e ações bem posicionados</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✅ Teste de Organização concluído!</strong> Siga as instruções manuais para validação completa.</p>";
?>
