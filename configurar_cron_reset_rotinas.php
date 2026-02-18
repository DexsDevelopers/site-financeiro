<?php
// configurar_cron_reset_rotinas.php - Configuração do cron job para reset automático

echo "<h1>🕛 Configuração do Reset Automático de Rotinas</h1>";

echo "<h2>📋 Instruções para Configurar Cron Job:</h2>";

echo "<div style='background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h3>1. Acesse o Painel da Hostinger</h3>";
echo "<p>• Vá para <strong>Cron Jobs</strong> no painel de controle</p>";
echo "<p>• Clique em <strong>Criar Novo Cron Job</strong></p>";
echo "</div>";

echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h3>2. Configure o Cron Job</h3>";
echo "<p><strong>Comando:</strong></p>";
echo "<code style='background: #f1f1f1; padding: 0.5rem; display: block; margin: 0.5rem 0;'>";
echo "php " . __DIR__ . "/reset_rotinas_meia_noite.php";
echo "</code>";
echo "<p><strong>Horário:</strong> 00:00 (meia-noite)</p>";
echo "<p><strong>Frequência:</strong> Diariamente</p>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h3>3. Teste Manual</h3>";
echo "<p>Para testar se está funcionando, execute:</p>";
echo "<a href='reset_rotinas_meia_noite.php' target='_blank' style='background: #007bff; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; display: inline-block;'>";
echo "🧪 Testar Reset Manual";
echo "</a>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h3>⚠️ Importante</h3>";
echo "<p>• O cron job deve ser executado <strong>diariamente à meia-noite</strong></p>";
echo "<p>• Certifique-se de que o caminho do PHP está correto</p>";
echo "<p>• Verifique os logs em <code>/logs/reset_rotinas_YYYY-MM.log</code></p>";
echo "</div>";

echo "<h2>🎯 O que o Reset Faz:</h2>";
echo "<ul>";
echo "<li>✅ Cria controles diários para todas as rotinas fixas ativas</li>";
echo "<li>✅ Define status inicial como 'pendente'</li>";
echo "<li>✅ Funciona para todos os usuários automaticamente</li>";
echo "<li>✅ Registra logs de execução</li>";
echo "</ul>";

echo "<h2>📊 Monitoramento:</h2>";
echo "<p>Para verificar se está funcionando:</p>";
echo "<ul>";
echo "<li>📁 Verifique os logs em <code>/logs/</code></li>";
echo "<li>🔄 Acesse a página de tarefas após a meia-noite</li>";
echo "<li>✅ Todas as rotinas devem aparecer como 'pendentes'</li>";
echo "</ul>";
?>
