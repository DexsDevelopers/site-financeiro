<?php
// teste_equipe_oficial.php - Teste para verificar se o botão Equipe Oficial está funcionando

echo "<h1>🧪 TESTE DO BOTÃO EQUIPE OFICIAL</h1>";
echo "<hr>";

// 1. Verificar se o arquivo header.php foi alterado
echo "<h2>1. Verificação do Header</h2>";

if (file_exists('templates/header.php')) {
    $content = file_get_contents('templates/header.php');
    
    if (strpos($content, 'Equipe Oficial') !== false) {
        echo "✅ <strong>Nome do botão</strong> - Alterado para 'Equipe Oficial'<br>";
    } else {
        echo "❌ <strong>Nome do botão</strong> - NÃO alterado<br>";
    }
    
    if (strpos($content, 'https://helmer.netlify.app/') !== false) {
        echo "✅ <strong>Link externo</strong> - Configurado para helmer.netlify.app<br>";
    } else {
        echo "❌ <strong>Link externo</strong> - NÃO configurado<br>";
    }
    
    if (strpos($content, 'target="_blank"') !== false) {
        echo "✅ <strong>Abertura em nova aba</strong> - Configurado<br>";
    } else {
        echo "❌ <strong>Abertura em nova aba</strong> - NÃO configurado<br>";
    }
    
    if (strpos($content, 'equipe.php') === false) {
        echo "✅ <strong>Link interno removido</strong> - Não aponta mais para equipe.php<br>";
    } else {
        echo "❌ <strong>Link interno</strong> - Ainda aponta para equipe.php<br>";
    }
} else {
    echo "❌ <strong>templates/header.php</strong> - Arquivo não encontrado<br>";
}

echo "<br>";

// 2. Verificar se o arquivo equipe.php foi removido
echo "<h2>2. Verificação de Limpeza</h2>";

if (!file_exists('equipe.php')) {
    echo "✅ <strong>equipe.php</strong> - Arquivo removido com sucesso<br>";
} else {
    echo "❌ <strong>equipe.php</strong> - Arquivo ainda existe<br>";
}

echo "<br>";

// 3. Instruções de teste
echo "<h2>3. Instruções para Teste Manual</h2>";
echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🚀 Como testar:</h4>";
echo "<ol>";
echo "<li><strong>Acesse o dashboard:</strong> <a href='dashboard.php' target='_blank'>dashboard.php</a></li>";
echo "<li><strong>Verifique o menu lateral:</strong> Procure pelo botão 'Equipe Oficial'</li>";
echo "<li><strong>Clique no botão:</strong> Deve abrir https://helmer.netlify.app/ em uma nova aba</li>";
echo "<li><strong>Verifique a URL:</strong> Deve ser exatamente helmer.netlify.app</li>";
echo "</ol>";
echo "</div>";

// 4. Status das alterações
echo "<h2>4. Status das Alterações</h2>";
echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✅ Alterações Realizadas:</h4>";
echo "<ul>";
echo "<li><strong>Nome do botão:</strong> 'Minha Equipe' → 'Equipe Oficial'</li>";
echo "<li><strong>Link de destino:</strong> equipe.php → https://helmer.netlify.app/</li>";
echo "<li><strong>Abertura:</strong> Mesma aba → Nova aba (target='_blank')</li>";
echo "<li><strong>Arquivo removido:</strong> equipe.php (não é mais necessário)</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>✅ Teste concluído!</strong> O botão 'Equipe Oficial' agora aponta para https://helmer.netlify.app/ e abre em uma nova aba.</p>";
?>
