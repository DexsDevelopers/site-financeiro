<?php
// corrigir_estatisticas.php - Correção automática dos problemas das APIs de estatísticas

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔧 CORREÇÃO AUTOMÁTICA DAS APIs DE ESTATÍSTICAS</h2>";

// 1. Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Redirecionando para login...<br>";
    echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";
    exit();
}

echo "✅ Usuário logado: ID " . $_SESSION['user_id'] . "<br>";

// 2. Verificar e criar tabela tarefas se necessário
echo "<h3>2. Verificação da Tabela 'tarefas'</h3>";
try {
    $stmt = $pdo->query("DESCRIBE tarefas");
    echo "✅ Tabela 'tarefas' existe<br>";
} catch (Exception $e) {
    echo "⚠️ Tabela 'tarefas' não existe. Criando...<br>";
    
    $sql = "CREATE TABLE IF NOT EXISTS tarefas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        descricao TEXT NOT NULL,
        prioridade ENUM('Alta', 'Média', 'Baixa') DEFAULT 'Média',
        status ENUM('pendente', 'concluida', 'cancelada') DEFAULT 'pendente',
        data_limite DATE,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_conclusao TIMESTAMP NULL,
        tempo_estimado INT DEFAULT 0,
        tempo_gasto INT DEFAULT 0,
        INDEX idx_usuario (id_usuario),
        INDEX idx_status (status),
        INDEX idx_prioridade (prioridade)
    )";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'tarefas' criada com sucesso<br>";
}

// 3. Verificar se há tarefas para o usuário
echo "<h3>3. Verificação de Dados</h3>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch()['total'];
    
    if ($total == 0) {
        echo "⚠️ Nenhuma tarefa encontrada. Criando tarefas de exemplo...<br>";
        
        // Criar algumas tarefas de exemplo
        $tarefasExemplo = [
            [
                'descricao' => 'Revisar relatório mensal',
                'prioridade' => 'Alta',
                'status' => 'pendente',
                'data_limite' => date('Y-m-d'),
                'tempo_estimado' => 120
            ],
            [
                'descricao' => 'Organizar arquivos do projeto',
                'prioridade' => 'Média',
                'status' => 'concluida',
                'data_limite' => date('Y-m-d', strtotime('-1 day')),
                'tempo_estimado' => 60,
                'data_conclusao' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'descricao' => 'Planejar reunião da próxima semana',
                'prioridade' => 'Baixa',
                'status' => 'pendente',
                'data_limite' => date('Y-m-d', strtotime('+2 days')),
                'tempo_estimado' => 30
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO tarefas (id_usuario, descricao, prioridade, status, data_limite, tempo_estimado, data_conclusao) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($tarefasExemplo as $tarefa) {
            $stmt->execute([
                $_SESSION['user_id'],
                $tarefa['descricao'],
                $tarefa['prioridade'],
                $tarefa['status'],
                $tarefa['data_limite'],
                $tarefa['tempo_estimado'],
                $tarefa['data_conclusao'] ?? null
            ]);
        }
        
        echo "✅ Tarefas de exemplo criadas com sucesso<br>";
    } else {
        echo "✅ Total de tarefas encontradas: " . $total . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao verificar/criar tarefas: " . $e->getMessage() . "<br>";
}

// 4. Testar as APIs após correções
echo "<h3>4. Teste das APIs Após Correções</h3>";

// Teste 1: buscar_tarefas_hoje.php
echo "<h4>Teste: buscar_tarefas_hoje.php</h4>";
try {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/buscar_tarefas_hoje.php";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        echo "✅ API funcionando<br>";
        echo "Tarefas encontradas: " . count($data['tarefas']) . "<br>";
    } else {
        echo "❌ API ainda com erro: " . $response . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
}

// Teste 2: buscar_distribuicao_prioridade.php
echo "<h4>Teste: buscar_distribuicao_prioridade.php</h4>";
try {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/buscar_distribuicao_prioridade.php";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        echo "✅ API funcionando<br>";
        echo "Distribuição: Alta=" . $data['alta'] . ", Média=" . $data['media'] . ", Baixa=" . $data['baixa'] . "<br>";
    } else {
        echo "❌ API ainda com erro: " . $response . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
}

// Teste 3: buscar_produtividade_7_dias.php
echo "<h4>Teste: buscar_produtividade_7_dias.php</h4>";
try {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/buscar_produtividade_7_dias.php";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data && isset($data['success'])) {
        echo "✅ API funcionando<br>";
        echo "Dados de produtividade carregados<br>";
    } else {
        echo "❌ API ainda com erro: " . $response . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro ao testar API: " . $e->getMessage() . "<br>";
}

echo "<br><h3>🎯 RESULTADO</h3>";
echo "Se todas as APIs estão funcionando agora, o problema foi resolvido!<br>";
echo "Se ainda há erros, verifique os logs de erro do servidor.<br>";
echo "<br><a href='tarefas.php' class='btn btn-primary'>Voltar para Tarefas</a>";
?>