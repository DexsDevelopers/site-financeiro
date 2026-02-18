<?php
// exemplo_interface_rotinas.php - Exemplo de como ficará a interface com rotinas fixas e de hoje

echo "<h1>📋 EXEMPLO DE INTERFACE - ROTINAS FIXAS + ROTINAS DE HOJE</h1>";
echo "<hr>";

echo "<div style='background: #f8f9fa; padding: 2rem; border-radius: 10px; margin: 1rem 0;'>";
echo "<h2>🎯 ROTINAS FIXAS (Hábitos Permanentes)</h2>";
echo "<p><strong>Estas rotinas aparecem sempre na página de tarefas</strong></p>";
echo "<div style='background: white; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #28a745;'>";
echo "<h4>✅ Hábitos Diários</h4>";
echo "<div style='display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; padding: 0.5rem; background: #e8f5e8; border-radius: 5px;'>";
echo "<div><strong>🌅 Treinar</strong> <small>(06:00)</small></div>";
echo "<div>";
echo "<button style='background: #28a745; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>✅ Concluído</button>";
echo "<button style='background: #ffc107; color: black; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>⏭️ Pular</button>";
echo "</div>";
echo "</div>";

echo "<div style='display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; padding: 0.5rem; background: #fff3cd; border-radius: 5px;'>";
echo "<div><strong>📚 Estudar</strong> <small>(08:00)</small></div>";
echo "<div>";
echo "<button style='background: #6c757d; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>⏳ Pendente</button>";
echo "<button style='background: #ffc107; color: black; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>⏭️ Pular</button>";
echo "</div>";
echo "</div>";

echo "<div style='display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; padding: 0.5rem; background: #f8d7da; border-radius: 5px;'>";
echo "<div><strong>📖 Ler</strong> <small>(20:00)</small></div>";
echo "<div>";
echo "<button style='background: #dc3545; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>❌ Pulado</button>";
echo "<button style='background: #28a745; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>✅ Refazer</button>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>📊 Progresso das Rotinas Fixas</h4>";
echo "<div style='background: #f8f9fa; padding: 1rem; border-radius: 5px;'>";
echo "<div style='display: flex; justify-content: space-between; margin-bottom: 0.5rem;'>";
echo "<span>1 de 3 concluídas</span>";
echo "<span>33%</span>";
echo "</div>";
echo "<div style='background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;'>";
echo "<div style='background: linear-gradient(90deg, #28a745 33%, #e9ecef 33%); height: 100%;'></div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 2rem; border-radius: 10px; margin: 1rem 0;'>";
echo "<h2>📅 ROTINAS DE HOJE (Específicas do Dia)</h2>";
echo "<p><strong>Estas rotinas são criadas automaticamente para hoje</strong></p>";
echo "<div style='background: white; padding: 1rem; border-radius: 8px; margin: 1rem 0; border-left: 4px solid #ffc107;'>";
echo "<h4>📋 Tarefas de Hoje</h4>";
echo "<div style='display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; padding: 0.5rem; background: #e8f5e8; border-radius: 5px;'>";
echo "<div><strong>🏃‍♂️ Corrida matinal</strong> <small>(06:30)</small></div>";
echo "<div>";
echo "<button style='background: #28a745; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>✅ Concluído</button>";
echo "<button style='background: #ffc107; color: black; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>⏭️ Pular</button>";
echo "</div>";
echo "</div>";

echo "<div style='display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; padding: 0.5rem; background: #fff3cd; border-radius: 5px;'>";
echo "<div><strong>🛒 Compras no supermercado</strong> <small>(14:00)</small></div>";
echo "<div>";
echo "<button style='background: #6c757d; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>⏳ Pendente</button>";
echo "<button style='background: #ffc107; color: black; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>⏭️ Pular</button>";
echo "</div>";
echo "</div>";

echo "<div style='display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0; padding: 0.5rem; background: #f8d7da; border-radius: 5px;'>";
echo "<div><strong>📞 Ligar para o médico</strong> <small>(16:00)</small></div>";
echo "<div>";
echo "<button style='background: #dc3545; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>❌ Pulado</button>";
echo "<button style='background: #28a745; color: white; border: none; padding: 0.3rem 0.8rem; border-radius: 4px; margin: 0 0.2rem;'>✅ Refazer</button>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>📊 Progresso das Rotinas de Hoje</h4>";
echo "<div style='background: #f8f9fa; padding: 1rem; border-radius: 5px;'>";
echo "<div style='display: flex; justify-content: space-between; margin-bottom: 0.5rem;'>";
echo "<span>1 de 3 concluídas</span>";
echo "<span>33%</span>";
echo "</div>";
echo "<div style='background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;'>";
echo "<div style='background: linear-gradient(90deg, #ffc107 33%, #e9ecef 33%); height: 100%;'></div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div style='background: #d1ecf1; padding: 2rem; border-radius: 10px; margin: 1rem 0;'>";
echo "<h2>🎯 RESUMO GERAL</h2>";
echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;'>";
echo "<div style='background: white; padding: 1rem; border-radius: 8px; text-align: center;'>";
echo "<h3 style='color: #28a745;'>Rotinas Fixas</h3>";
echo "<p><strong>1/3 concluídas</strong></p>";
echo "<p style='color: #6c757d;'>Hábitos permanentes</p>";
echo "</div>";
echo "<div style='background: white; padding: 1rem; border-radius: 8px; text-align: center;'>";
echo "<h3 style='color: #ffc107;'>Rotinas de Hoje</h3>";
echo "<p><strong>1/3 concluídas</strong></p>";
echo "<p style='color: #6c757d;'>Tarefas específicas</p>";
echo "</div>";
echo "</div>";
echo "<div style='background: white; padding: 1rem; border-radius: 8px; margin-top: 1rem; text-align: center;'>";
echo "<h3 style='color: #007bff;'>Progresso Total</h3>";
echo "<p><strong>2/6 concluídas (33%)</strong></p>";
echo "<div style='background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; margin-top: 0.5rem;'>";
echo "<div style='background: linear-gradient(90deg, #007bff 33%, #e9ecef 33%); height: 100%;'></div>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🔧 Funcionalidades Disponíveis:</h4>";
echo "<ul>";
echo "<li><strong>Rotinas Fixas:</strong> Sempre aparecem, controle diário de execução</li>";
echo "<li><strong>Rotinas de Hoje:</strong> Específicas do dia, criadas automaticamente</li>";
echo "<li><strong>Status:</strong> Pendente, Concluído, Pulado</li>";
echo "<li><strong>Horários:</strong> Sugeridos e de execução</li>";
echo "<li><strong>Observações:</strong> Notas para cada rotina</li>";
echo "<li><strong>Progresso:</strong> Individual e geral</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✅ Vantagens do Sistema Duplo:</h4>";
echo "<ol>";
echo "<li><strong>Hábitos Permanentes:</strong> Rotinas fixas para hábitos diários</li>";
echo "<li><strong>Flexibilidade:</strong> Rotinas de hoje para tarefas específicas</li>";
echo "<li><strong>Organização:</strong> Separação clara entre hábitos e tarefas</li>";
echo "<li><strong>Controle:</strong> Acompanhamento individual de cada tipo</li>";
echo "<li><strong>Progresso:</strong> Métricas separadas e combinadas</li>";
echo "</ol>";
echo "</div>";
?>
