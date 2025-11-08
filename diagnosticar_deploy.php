<?php
// diagnosticar_deploy.php - Diagnosticar problemas de deploy

echo "<h1>🔍 DIAGNÓSTICO DE DEPLOY</h1>";
echo "<hr>";

echo "<h3>📊 Informações do Sistema</h3>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Diretório atual:</strong> " . getcwd() . "</p>";

echo "<h3>📁 Arquivos no diretório</h3>";
$arquivos = scandir('.');
echo "<ul>";
foreach ($arquivos as $arquivo) {
    if ($arquivo != '.' && $arquivo != '..') {
        echo "<li>$arquivo</li>";
    }
}
echo "</ul>";

echo "<h3>🔧 Teste de conectividade</h3>";
// Usa a mesma conexão do sistema (inclui credenciais corretas)
try {
    require_once __DIR__ . '/includes/db_connect.php';
    // Teste simples
    $ok = $pdo->query('SELECT 1')->fetchColumn();
    if ($ok == 1) {
        echo "<p>✅ <strong>Conexão com banco de dados:</strong> OK</p>";
    } else {
        echo "<p>❌ <strong>Conexão estabelecida, mas teste falhou.</strong></p>";
    }
} catch (Throwable $e) {
    echo "<p>❌ <strong>Erro de conexão:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>🌐 Teste de URLs</h3>";
$urls_teste = [
    'https://gold-quail-250128.hostingersite.com/',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/index.php',
    'https://gold-quail-250128.hostingersite.com/seu_projeto/tarefas.php'
];

foreach ($urls_teste as $url) {
    echo "<p><strong>Testando:</strong> $url</p>";
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $resultado = @file_get_contents($url, false, $context);
    if ($resultado !== false) {
        echo "<p>✅ <strong>Resposta:</strong> OK</p>";
    } else {
        echo "<p>❌ <strong>Erro:</strong> Não foi possível acessar</p>";
    }
}

echo "<h3>📋 Próximos passos</h3>";
echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🔧 Possíveis soluções:</h4>";
echo "<ol>";
echo "<li><strong>Verificar configuração do servidor</strong> - Pode estar bloqueando acesso</li>";
echo "<li><strong>Aguardar deploy</strong> - Pode estar em andamento</li>";
echo "<li><strong>Verificar URL correta</strong> - Pode ter mudado</li>";
echo "<li><strong>Contatar suporte Hostinger</strong> - Pode ser problema do servidor</li>";
echo "</ol>";
echo "</div>";
?>
