<?php
// atualizar_javascript_estatisticas.php - Atualizar JavaScript para usar APIs alternativas

echo "<h2>🔧 ATUALIZAÇÃO DO JAVASCRIPT DAS ESTATÍSTICAS</h2>";

// Verificar se o arquivo tarefas.php existe
if (!file_exists('tarefas.php')) {
    echo "❌ Arquivo tarefas.php não encontrado<br>";
    exit();
}

echo "✅ Arquivo tarefas.php encontrado<br>";

// Ler o conteúdo atual
$conteudo = file_get_contents('tarefas.php');

// Verificar se já foi atualizado
if (strpos($conteudo, 'api_tarefas_hoje.php') !== false) {
    echo "✅ JavaScript já foi atualizado para usar APIs alternativas<br>";
    echo "<a href='tarefas.php' class='btn btn-success'>Voltar para Tarefas</a>";
    exit();
}

// Atualizar as URLs das APIs
$atualizacoes = [
    'buscar_tarefas_hoje.php' => 'api_tarefas_hoje.php',
    'buscar_distribuicao_prioridade.php' => 'api_distribuicao_prioridade.php',
    'buscar_produtividade_7_dias.php' => 'api_produtividade_7_dias.php'
];

$conteudoAtualizado = $conteudo;

foreach ($atualizacoes as $antiga => $nova) {
    $conteudoAtualizado = str_replace($antiga, $nova, $conteudoAtualizado);
}

// Salvar o arquivo atualizado
if (file_put_contents('tarefas.php', $conteudoAtualizado)) {
    echo "✅ JavaScript atualizado com sucesso<br>";
    
    // Verificar se as atualizações foram aplicadas
    $conteudoVerificacao = file_get_contents('tarefas.php');
    
    $atualizacoesAplicadas = 0;
    foreach ($atualizacoes as $antiga => $nova) {
        if (strpos($conteudoVerificacao, $nova) !== false) {
            echo "✅ $antiga → $nova<br>";
            $atualizacoesAplicadas++;
        } else {
            echo "❌ $antiga → $nova (não aplicado)<br>";
        }
    }
    
    if ($atualizacoesAplicadas === count($atualizacoes)) {
        echo "<br><h3>🎉 TODAS AS ATUALIZAÇÕES FORAM APLICADAS!</h3>";
        echo "<p>O JavaScript das estatísticas foi atualizado para usar as APIs alternativas.</p>";
        echo "<p>Agora você pode:</p>";
        echo "<ul>";
        echo "<li>Acessar a página de tarefas</li>";
        echo "<li>Clicar no botão 'Estatísticas'</li>";
        echo "<li>Verificar se o modal carrega os dados corretamente</li>";
        echo "</ul>";
        echo "<a href='tarefas.php' class='btn btn-success'>Testar Estatísticas</a>";
    } else {
        echo "<br><h3>⚠️ ALGUMAS ATUALIZAÇÕES NÃO FORAM APLICADAS</h3>";
        echo "Verifique manualmente o arquivo tarefas.php<br>";
    }
} else {
    echo "❌ Erro ao salvar o arquivo atualizado<br>";
}

// Mostrar as mudanças realizadas
echo "<h3>📋 MUDANÇAS REALIZADAS:</h3>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🔄 URLs Atualizadas:</h4>";
echo "<ul>";
echo "<li><strong>buscar_tarefas_hoje.php</strong> → <strong>api_tarefas_hoje.php</strong></li>";
echo "<li><strong>buscar_distribuicao_prioridade.php</strong> → <strong>api_distribuicao_prioridade.php</strong></li>";
echo "<li><strong>buscar_produtividade_7_dias.php</strong> → <strong>api_produtividade_7_dias.php</strong></li>";
echo "</ul>";
echo "</div>";

echo "<h3>🎯 PRÓXIMOS PASSOS:</h3>";
echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✅ Como testar:</h4>";
echo "<ol>";
echo "<li><strong>Acesse a página de tarefas:</strong> <a href='tarefas.php' target='_blank'>tarefas.php</a></li>";
echo "<li><strong>Clique no botão 'Estatísticas':</strong> Deve abrir o modal</li>";
echo "<li><strong>Verifique se os dados carregam:</strong> Tarefas de hoje, distribuição por prioridade, gráfico de produtividade</li>";
echo "<li><strong>Teste a responsividade:</strong> Verifique se funciona bem no mobile</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✅ Atualização concluída!</strong> O JavaScript das estatísticas foi atualizado para usar as APIs alternativas.</p>";
?>
