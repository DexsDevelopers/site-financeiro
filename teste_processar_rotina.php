<?php
session_start();
$_SESSION['user_id'] = 1;

require_once 'includes/db_connect.php';
require_once 'includes/rotina_fixa_functions.php';

echo "<h1>Teste Processar Rotina</h1>";

// Testar se a função existe
if (function_exists('adicionarRotinaFixa')) {
    echo "<p>✅ Função adicionarRotinaFixa existe</p>";
} else {
    echo "<p>❌ Função adicionarRotinaFixa NÃO existe</p>";
}

// Testar conexão com banco
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch();
    echo "<p>✅ Conexão BD funcionando - Total usuários: " . $result['total'] . "</p>";
} catch (Exception $e) {
    echo "<p>❌ Erro na conexão BD: " . $e->getMessage() . "</p>";
}

// Testar se as tabelas existem
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rotinas_fixas'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Tabela rotinas_fixas existe</p>";
    } else {
        echo "<p>❌ Tabela rotinas_fixas NÃO existe</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro ao verificar tabela: " . $e->getMessage() . "</p>";
}

// Testar inserção
try {
    $nome = "Teste Rotina";
    $horario = "08:00:00";
    $descricao = "Descrição de teste";
    
    $idRotina = adicionarRotinaFixa($pdo, 1, $nome, $horario, $descricao, 0);
    
    if ($idRotina) {
        echo "<p>✅ Rotina adicionada com sucesso - ID: " . $idRotina . "</p>";
        
        // Verificar se foi inserida
        $stmt = $pdo->prepare("SELECT * FROM rotinas_fixas WHERE id = ?");
        $stmt->execute([$idRotina]);
        $rotina = $stmt->fetch();
        
        if ($rotina) {
            echo "<p>✅ Rotina encontrada no banco:</p>";
            echo "<ul>";
            echo "<li>Nome: " . $rotina['nome'] . "</li>";
            echo "<li>Horário: " . $rotina['horario_sugerido'] . "</li>";
            echo "<li>Descrição: " . $rotina['descricao'] . "</li>";
            echo "</ul>";
        } else {
            echo "<p>❌ Rotina não encontrada no banco</p>";
        }
    } else {
        echo "<p>❌ Erro ao adicionar rotina</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erro na inserção: " . $e->getMessage() . "</p>";
}
?>
