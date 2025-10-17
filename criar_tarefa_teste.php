
<?php
session_start();
require_once 'includes/db_connect.php';

// Pegar ID do usuário logado
$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$userId) {
    echo "<h2>❌ Erro: Você não está logado!</h2>";
    echo "<p><a href='index.php'>Faça login primeiro</a></p>";
    exit;
}

try {
    // Tarefas de teste
    $tarefas_teste = [
        [
            'titulo' => '📝 Revisar documentação',
            'descricao' => 'Revisar e atualizar a documentação do projeto',
            'prioridade' => 'Alta',
            'data_limite' => date('Y-m-d', strtotime('+3 days')),
            'tempo_estimado' => 120 // 2 horas
        ],
        [
            'titulo' => '🐛 Corrigir bugs da aplicação',
            'descricao' => 'Identificar e corrigir bugs reportados pelos usuários',
            'prioridade' => 'Alta',
            'data_limite' => date('Y-m-d', strtotime('+5 days')),
            'tempo_estimado' => 240 // 4 horas
        ],
        [
            'titulo' => '📊 Preparar relatório mensal',
            'descricao' => 'Compilar dados e preparar relatório de progresso',
            'prioridade' => 'Média',
            'data_limite' => date('Y-m-d', strtotime('+7 days')),
            'tempo_estimado' => 180 // 3 horas
        ],
        [
            'titulo' => '💬 Responder emails pendentes',
            'descricao' => 'Responder todos os emails da caixa de entrada',
            'prioridade' => 'Média',
            'data_limite' => date('Y-m-d', strtotime('+1 day')),
            'tempo_estimado' => 60 // 1 hora
        ],
        [
            'titulo' => '🎨 Atualizar design da interface',
            'descricao' => 'Implementar novo design conforme aprovado',
            'prioridade' => 'Baixa',
            'data_limite' => date('Y-m-d', strtotime('+10 days')),
            'tempo_estimado' => 300 // 5 horas
        ],
        [
            'titulo' => '📚 Estudar nova tecnologia',
            'descricao' => 'Aprender sobre a nova stack que vamos usar',
            'prioridade' => 'Baixa',
            'data_limite' => date('Y-m-d', strtotime('+14 days')),
            'tempo_estimado' => 420 // 7 horas
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO tarefas (id_usuario, titulo, descricao, prioridade, data_limite, tempo_estimado, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pendente')
    ");

    echo "<h2>✨ Criando Tarefas de Teste</h2>";
    echo "<hr>";

    foreach ($tarefas_teste as $tarefa) {
        $stmt->execute([
            $userId,
            $tarefa['titulo'],
            $tarefa['descricao'],
            $tarefa['prioridade'],
            $tarefa['data_limite'],
            $tarefa['tempo_estimado']
        ]);
        
        echo "✅ Criada: <strong>{$tarefa['titulo']}</strong><br>";
    }

    echo "<hr>";
    echo "<h3>✨ Sucesso! 6 tarefas de teste criadas!</h3>";
    echo "<p><a href='tarefas_otimizado.php' style='font-weight: bold; color: #dc3545;'>👉 Clique aqui para ver as tarefas</a></p>";
    
} catch (PDOException $e) {
    echo "<h2>❌ Erro ao criar tarefas</h2>";
    echo "<p>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
