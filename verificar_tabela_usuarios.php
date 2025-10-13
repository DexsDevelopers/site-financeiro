<?php
// verificar_tabela_usuarios.php - Verificar estrutura da tabela usuarios

session_start();
require_once 'includes/db_connect.php';

echo "<h2>🔍 VERIFICANDO TABELA USUARIOS</h2>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "❌ Usuário não está logado. Faça login primeiro.<br>";
    exit();
}

$userId = $_SESSION['user_id'];
echo "✅ Usuário logado: ID $userId<br><br>";

// 1. Verificar estrutura da tabela usuarios
echo "<h3>1. Estrutura da Tabela 'usuarios'</h3>";

try {
    $stmt = $pdo->query("DESCRIBE usuarios");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Tabela 'usuarios' existe<br>";
    echo "📋 Colunas da tabela:<br>";
    foreach ($colunas as $coluna) {
        echo "&nbsp;&nbsp;- {$coluna['Field']} ({$coluna['Type']})<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro ao verificar tabela usuarios: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 2. Verificar dados do usuário atual
echo "<h3>2. Dados do Usuário Atual</h3>";

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        echo "✅ Usuário encontrado<br>";
        echo "📋 Dados do usuário:<br>";
        foreach ($usuario as $campo => $valor) {
            echo "&nbsp;&nbsp;- $campo: $valor<br>";
        }
    } else {
        echo "❌ Usuário não encontrado<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro ao buscar usuário: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 3. Testar consulta corrigida
echo "<h3>3. Testando Consulta Corrigida</h3>";

try {
    // Primeiro, vamos ver quais colunas existem na tabela usuarios
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $nomeColuna = null;
    foreach ($colunas as $coluna) {
        if (in_array($coluna['Field'], ['nome', 'nome_completo', 'name', 'full_name'])) {
            $nomeColuna = $coluna['Field'];
            break;
        }
    }
    
    if ($nomeColuna) {
        echo "✅ Coluna de nome encontrada: $nomeColuna<br>";
        
        // Testar consulta com a coluna correta
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                cm.papel,
                cm.status as status_membro,
                u.$nomeColuna as nome_proprietario
            FROM contas c
            JOIN conta_membros cm ON c.id = cm.conta_id
            LEFT JOIN usuarios u ON c.criado_por = u.id
            WHERE cm.usuario_id = ? AND cm.status = 'ativo'
            ORDER BY c.data_criacao DESC
        ");
        $stmt->execute([$userId]);
        $contasUsuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✅ Consulta executada com sucesso<br>";
        echo "📊 Contas encontradas: " . count($contasUsuario) . "<br>";
        
        if (!empty($contasUsuario)) {
            echo "📋 Contas do usuário:<br>";
            foreach ($contasUsuario as $conta) {
                echo "&nbsp;&nbsp;- ID: {$conta['id']}, Nome: {$conta['nome']}, Proprietário: {$conta['nome_proprietario']}<br>";
            }
        }
        
    } else {
        echo "❌ Nenhuma coluna de nome encontrada<br>";
        echo "📋 Colunas disponíveis:<br>";
        foreach ($colunas as $coluna) {
            echo "&nbsp;&nbsp;- {$coluna['Field']}<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro na consulta corrigida: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 4. Resumo e solução
echo "<h2>📊 RESUMO E SOLUÇÃO</h2>";

if ($nomeColuna) {
    echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>✅ PROBLEMA IDENTIFICADO E SOLUÇÃO</h4>";
    echo "<p><strong>Problema:</strong> A consulta estava tentando acessar 'u.nome' mas a coluna correta é 'u.$nomeColuna'</p>";
    echo "<p><strong>Solução:</strong> Atualizar a consulta para usar '$nomeColuna' em vez de 'nome'</p>";
    echo "<p><strong>Próximo passo:</strong> Corrigir os arquivos que fazem essa consulta</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h4>❌ PROBLEMA MAIS COMPLEXO</h4>";
    echo "<p>A tabela usuarios não possui uma coluna de nome padrão.</p>";
    echo "<p><strong>Possíveis soluções:</strong></p>";
    echo "<ul>";
    echo "<li>Adicionar coluna 'nome' na tabela usuarios</li>";
    echo "<li>Usar uma coluna existente como nome</li>";
    echo "<li>Modificar a consulta para não incluir o nome do proprietário</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>✅ Verificação da tabela usuarios concluída!</strong></p>";
?>
