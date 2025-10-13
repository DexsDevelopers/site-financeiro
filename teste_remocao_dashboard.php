<?php
// teste_remocao_dashboard.php - Teste de remoção dos elementos do dashboard

echo "<h2>🧪 TESTE DE REMOÇÃO DOS ELEMENTOS DO DASHBOARD</h2>";

// Verificar se o arquivo dashboard.php existe
if (!file_exists('dashboard.php')) {
    echo "❌ Arquivo dashboard.php não encontrado<br>";
    exit();
}

echo "✅ Arquivo dashboard.php encontrado<br><br>";

// Ler o conteúdo do arquivo
$conteudo = file_get_contents('dashboard.php');

// Verificar se os elementos foram removidos
$elementos_removidos = [
    'Rotina Diária' => [
        'texto' => 'Rotina Diária',
        'classe' => 'rotina-list',
        'funcao' => 'toggleRotina'
    ],
    'Pomodoro Timer' => [
        'texto' => 'Pomodoro Timer',
        'classe' => 'timer-circle-dashboard',
        'funcao' => 'startPomodoroQuick'
    ],
    'Organização por Horário' => [
        'texto' => 'Organização por Horário',
        'classe' => 'periodo-card-dashboard',
        'funcao' => 'periodos'
    ]
];

echo "<h3>🔍 VERIFICAÇÃO DOS ELEMENTOS REMOVIDOS</h3>";

$todos_removidos = true;

foreach ($elementos_removidos as $nome => $elementos) {
    echo "<h4>$nome</h4>";
    
    $removido = true;
    
    // Verificar texto
    if (strpos($conteudo, $elementos['texto']) !== false) {
        echo "❌ Texto '$elementos[texto]' ainda encontrado<br>";
        $removido = false;
    } else {
        echo "✅ Texto '$elementos[texto]' removido<br>";
    }
    
    // Verificar classe CSS
    if (strpos($conteudo, $elementos['classe']) !== false) {
        echo "❌ Classe CSS '$elementos[classe]' ainda encontrada<br>";
        $removido = false;
    } else {
        echo "✅ Classe CSS '$elementos[classe]' removida<br>";
    }
    
    // Verificar função JavaScript
    if (strpos($conteudo, $elementos['funcao']) !== false) {
        echo "❌ Função JavaScript '$elementos[funcao]' ainda encontrada<br>";
        $removido = false;
    } else {
        echo "✅ Função JavaScript '$elementos[funcao]' removida<br>";
    }
    
    if ($removido) {
        echo "✅ <strong>$nome removido com sucesso!</strong><br>";
    } else {
        echo "❌ <strong>$nome ainda presente no código</strong><br>";
        $todos_removidos = false;
    }
    
    echo "<hr>";
}

// Verificar se ainda existem referências aos elementos
echo "<h3>🔍 VERIFICAÇÃO DE REFERÊNCIAS RESTANTES</h3>";

$referencias_restantes = [
    'rotina_diaria.php',
    'pomodoro.php',
    'automatizacao_horario.php',
    'salvar_rotina_diaria.php',
    'criar_sessao_pomodoro.php'
];

$referencias_encontradas = [];

foreach ($referencias_restantes as $referencia) {
    if (strpos($conteudo, $referencia) !== false) {
        $referencias_encontradas[] = $referencia;
    }
}

if (empty($referencias_encontradas)) {
    echo "✅ Nenhuma referência aos arquivos removidos encontrada<br>";
} else {
    echo "⚠️ Referências ainda encontradas:<br>";
    foreach ($referencias_encontradas as $ref) {
        echo "&nbsp;&nbsp;- $ref<br>";
    }
}

// Verificar estrutura do dashboard
echo "<h3>📋 VERIFICAÇÃO DA ESTRUTURA DO DASHBOARD</h3>";

$elementos_principais = [
    'Resumo do Mês' => 'Resumo de',
    'Lançamento Rápido com IA' => 'Lançamento Rápido com IA',
    'Tarefas de Hoje' => 'Tarefas de Hoje',
    'Produtividade' => 'Produtividade',
    'Despesas por Categoria' => 'Despesas por Categoria',
    'Despesas Diárias' => 'Despesas Diárias',
    'Últimos Lançamentos' => 'Últimos Lançamentos'
];

$elementos_presentes = 0;

foreach ($elementos_principais as $nome => $texto) {
    if (strpos($conteudo, $texto) !== false) {
        echo "✅ $nome presente<br>";
        $elementos_presentes++;
    } else {
        echo "❌ $nome não encontrado<br>";
    }
}

// Resumo final
echo "<h2>📊 RESUMO FINAL</h2>";

if ($todos_removidos) {
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ Remoção bem-sucedida!</h4>";
    echo "<p>Todos os elementos solicitados foram removidos do dashboard:</p>";
    echo "<ul>";
    echo "<li>✅ Rotina Diária</li>";
    echo "<li>✅ Pomodoro Timer</li>";
    echo "<li>✅ Organização por Horário</li>";
    echo "</ul>";
    echo "<p>O dashboard agora contém apenas os elementos principais de finanças e tarefas.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>❌ Remoção incompleta</h4>";
    echo "<p>Alguns elementos ainda estão presentes no código. Verifique os itens marcados com ❌ acima.</p>";
    echo "</div>";
}

echo "<p><strong>Elementos principais presentes: $elementos_presentes/" . count($elementos_principais) . "</strong></p>";

echo "<hr>";
echo "<p><strong>✅ Teste concluído!</strong> Verifique os resultados acima.</p>";
?>
