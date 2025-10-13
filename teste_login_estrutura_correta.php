<?php
// teste_login_estrutura_correta.php - Teste do Login com Estrutura Correta
// Verifica se o login funciona com a estrutura real da tabela

// Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db_connect.php';

echo "<h2>🔐 TESTE DO LOGIN COM ESTRUTURA CORRETA</h2>";

// 1. Verificar estrutura da tabela
echo "<h3>1. ESTRUTURA DA TABELA 'usuarios'</h3>";

try {
    $stmt = $pdo->query("DESCRIBE usuarios");
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($colunas as $coluna) {
        echo "<tr>";
        echo "<td><strong>{$coluna['Field']}</strong></td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>❌ Erro ao verificar estrutura:</strong> " . $e->getMessage() . "</p>";
}

echo "<br>";

// 2. Testar consulta de login
echo "<h3>2. TESTE DA CONSULTA DE LOGIN</h3>";

try {
    // Consulta exata do login.php
    $stmt = $pdo->prepare("
        SELECT id, nome_completo as nome, email, senha_hash as senha, tipo as papel, role
        FROM usuarios 
        WHERE usuario = ?
    ");
    
    // Testar com um usuário existente
    $stmt->execute(['admin']); // Assumindo que existe um usuário 'admin'
    $usuarioData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuarioData) {
        echo "<p style='color: green;'><strong>✅ Consulta funcionando!</strong></p>";
        echo "<p><strong>Dados retornados:</strong></p>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$usuarioData['id']}</li>";
        echo "<li><strong>Nome:</strong> {$usuarioData['nome']}</li>";
        echo "<li><strong>Email:</strong> {$usuarioData['email']}</li>";
        echo "<li><strong>Papel:</strong> {$usuarioData['papel']}</li>";
        echo "<li><strong>Role:</strong> {$usuarioData['role']}</li>";
        echo "<li><strong>Senha Hash:</strong> " . (strlen($usuarioData['senha']) > 0 ? 'Presente (' . strlen($usuarioData['senha']) . ' chars)' : 'Vazia') . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'><strong>⚠️ Nenhum usuário encontrado com 'admin'</strong></p>";
        
        // Listar alguns usuários disponíveis
        $stmt = $pdo->query("SELECT usuario FROM usuarios LIMIT 5");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($usuarios)) {
            echo "<p><strong>Usuários disponíveis para teste:</strong></p>";
            echo "<ul>";
            foreach ($usuarios as $user) {
                echo "<li>{$user['usuario']}</li>";
            }
            echo "</ul>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>❌ Erro na consulta:</strong> " . $e->getMessage() . "</p>";
}

echo "<br>";

// 3. Testar validação de senha
echo "<h3>3. TESTE DE VALIDAÇÃO DE SENHA</h3>";

try {
    // Buscar um usuário para testar
    $stmt = $pdo->query("SELECT usuario, senha_hash FROM usuarios LIMIT 1");
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        echo "<p><strong>Testando com usuário:</strong> {$usuario['usuario']}</p>";
        
        // Testar com senha incorreta
        $senhaTeste = 'senha_incorreta';
        $resultado = password_verify($senhaTeste, $usuario['senha_hash']);
        
        if ($resultado) {
            echo "<p style='color: red;'><strong>❌ PROBLEMA: Senha incorreta foi aceita!</strong></p>";
        } else {
            echo "<p style='color: green;'><strong>✅ Validação funcionando: Senha incorreta rejeitada</strong></p>";
        }
        
        // Verificar se a senha hash está no formato correto
        if (password_get_info($usuario['senha_hash'])['algo'] !== null) {
            echo "<p style='color: green;'><strong>✅ Hash de senha válido</strong></p>";
        } else {
            echo "<p style='color: orange;'><strong>⚠️ Hash de senha pode estar em formato antigo</strong></p>";
        }
        
    } else {
        echo "<p style='color: red;'><strong>❌ Nenhum usuário encontrado para teste</strong></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>❌ Erro no teste de senha:</strong> " . $e->getMessage() . "</p>";
}

echo "<br>";

// 4. Testar sessão
echo "<h3>4. TESTE DE SESSÃO</h3>";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
    echo "<p style='color: green;'><strong>✅ Sessão iniciada</strong></p>";
} else {
    echo "<p style='color: green;'><strong>✅ Sessão já ativa</strong></p>";
}

// Verificar variáveis de sessão esperadas
$variaveisEsperadas = ['user_id', 'nome', 'email', 'papel', 'status'];
echo "<p><strong>Variáveis de sessão esperadas:</strong></p>";
echo "<ul>";
foreach ($variaveisEsperadas as $variavel) {
    if (isset($_SESSION[$variavel])) {
        echo "<li style='color: green;'>✅ \$_SESSION['$variavel'] = " . $_SESSION[$variavel] . "</li>";
    } else {
        echo "<li style='color: orange;'>❌ \$_SESSION['$variavel'] - NÃO DEFINIDA</li>";
    }
}
echo "</ul>";

echo "<br>";

// 5. Resumo e recomendações
echo "<h3>5. RESUMO E RECOMENDAÇÕES</h3>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>";
echo "<h4>📋 Status do Sistema:</h4>";

// Verificar se tudo está funcionando
$problemas = [];

// Verificar estrutura
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($total == 0) {
        $problemas[] = "Nenhum usuário na tabela";
    }
} catch (PDOException $e) {
    $problemas[] = "Erro de conexão com banco";
}

// Verificar coluna usuario
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE usuario IS NOT NULL AND usuario != ''");
    $totalComUsuario = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($totalComUsuario == 0) {
        $problemas[] = "Nenhum usuário tem campo 'usuario' preenchido";
    }
} catch (PDOException $e) {
    $problemas[] = "Erro ao verificar campo usuario";
}

if (empty($problemas)) {
    echo "<p style='color: green;'><strong>✅ Sistema funcionando corretamente!</strong></p>";
    echo "<p>Você pode fazer login com usuário e senha.</p>";
    echo "<p><a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔐 Ir para o Login</a></p>";
} else {
    echo "<p style='color: red;'><strong>⚠️ Problemas encontrados:</strong></p>";
    echo "<ul>";
    foreach ($problemas as $problema) {
        echo "<li>$problema</li>";
    }
    echo "</ul>";
    
    echo "<p><strong>🔧 Soluções:</strong></p>";
    echo "<ol>";
    echo "<li>Execute <a href='verificar_coluna_usuario.php'>verificar_coluna_usuario.php</a> para corrigir problemas</li>";
    echo "<li>Verifique se há usuários na tabela</li>";
    echo "<li>Confirme se o campo 'usuario' está preenchido</li>";
    echo "</ol>";
}

echo "</div>";

echo "<br>";
echo "<p><strong>✅ Teste concluído!</strong></p>";
?>

