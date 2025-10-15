<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'templates/header.php';

echo "<h1>Debug Hábitos Diários</h1>";
echo "<div style='background: #1a1a1a; color: #fff; padding: 20px; border-radius: 10px; margin: 20px;'>";

echo "<h2>1. Verificação de Sessão</h2>";
echo "<pre>";
echo "user_id: " . (isset($userId) ? $userId : 'NÃO DEFINIDO') . "\n";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NÃO EXISTE') . "\n";
echo "Session user[id]: " . ($_SESSION['user']['id'] ?? 'NÃO EXISTE') . "\n";
echo "</pre>";

echo "<h2>2. Verificação do Banco de Dados</h2>";
echo "<pre>";
try {
    // Verificar conexão
    if (!isset($pdo)) {
        echo "❌ PDO não está definido\n";
    } else {
        echo "✅ PDO conectado\n";
        
        // Verificar tabelas
        $stmt = $pdo->query("SHOW TABLES LIKE 'rotinas_fixas'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabela rotinas_fixas existe\n";
        } else {
            echo "❌ Tabela rotinas_fixas NÃO existe\n";
        }
        
        $stmt = $pdo->query("SHOW TABLES LIKE 'rotina_controle_diario'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabela rotina_controle_diario existe\n";
        } else {
            echo "❌ Tabela rotina_controle_diario NÃO existe\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h2>3. Buscar Rotinas do Usuário</h2>";
echo "<pre>";
try {
    $dataHoje = date('Y-m-d');
    
    if (!isset($userId) || empty($userId)) {
        echo "❌ userId não está definido ou está vazio\n";
        echo "Tentando usar \$_SESSION...\n";
        $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
        echo "userId agora: " . ($userId ?? 'AINDA VAZIO') . "\n";
    }
    
    if ($userId) {
        echo "✅ Buscando rotinas para userId: {$userId}\n";
        echo "Data de hoje: {$dataHoje}\n\n";
        
        $stmt = $pdo->prepare("
            SELECT rf.*, rcd.status as status_hoje
            FROM rotinas_fixas rf
            LEFT JOIN rotina_controle_diario rcd 
                ON rf.id = rcd.id_rotina_fixa 
                AND rcd.id_usuario = rf.id_usuario 
                AND rcd.data_execucao = ?
            WHERE rf.id_usuario = ? AND rf.ativo = TRUE
            ORDER BY rf.ordem, rf.horario_sugerido
        ");
        $stmt->execute([$dataHoje, $userId]);
        $rotinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Rotinas encontradas: " . count($rotinas) . "\n\n";
        
        if (count($rotinas) > 0) {
            foreach ($rotinas as $rotina) {
                echo "ID: {$rotina['id']}\n";
                echo "Nome: {$rotina['nome']}\n";
                echo "Horário: " . ($rotina['horario_sugerido'] ?? 'N/A') . "\n";
                echo "Status Hoje: " . ($rotina['status_hoje'] ?? 'pendente') . "\n";
                echo "---\n";
            }
        } else {
            echo "ℹ️ Nenhuma rotina encontrada para este usuário\n";
            
            // Verificar se o usuário tem rotinas cadastradas
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rotinas_fixas WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $total = $stmt->fetchColumn();
            
            if ($total == 0) {
                echo "\n📝 O usuário não tem nenhuma rotina cadastrada no banco.\n";
                echo "Adicione uma rotina fixa primeiro!\n";
            } else {
                echo "\n⚠️ O usuário tem {$total} rotina(s) cadastrada(s), mas algo está errado.\n";
            }
        }
    } else {
        echo "❌ Não foi possível determinar o userId\n";
    }
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
echo "</pre>";

echo "<h2>4. Estrutura das Tabelas</h2>";
echo "<pre>";
try {
    // Estrutura rotinas_fixas
    echo "=== ROTINAS_FIXAS ===\n";
    $stmt = $pdo->query("DESCRIBE rotinas_fixas");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']}\n";
    }
    
    echo "\n=== ROTINA_CONTROLE_DIARIO ===\n";
    $stmt = $pdo->query("DESCRIBE rotina_controle_diario");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']}\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao buscar estrutura: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "</div>";

require_once 'templates/footer.php';
?>

