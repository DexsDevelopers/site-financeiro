<?php
// verificar_habitos_usuario.php - Verificar hábitos do usuário atual

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🔍 Verificação de Hábitos do Usuário</h2>";
echo "<p><strong>Usuário ID:</strong> $userId</p>";

// Verificar hábitos fixos
echo "<h3>📋 Hábitos Fixos (rotinas_fixas):</h3>";

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, horario_sugerido, descricao, ativo, data_criacao
        FROM rotinas_fixas 
        WHERE id_usuario = ? 
        ORDER BY data_criacao DESC
    ");
    $stmt->execute([$userId]);
    $habitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($habitos)) {
        echo "<p>❌ Nenhum hábito fixo encontrado.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>ID</th><th>Nome</th><th>Horário</th><th>Descrição</th><th>Ativo</th><th>Criado em</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($habitos as $habito) {
            $ativo = $habito['ativo'] ? '✅ Sim' : '❌ Não';
            echo "<tr>";
            echo "<td>" . $habito['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($habito['nome']) . "</strong></td>";
            echo "<td>" . ($habito['horario_sugerido'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars($habito['descricao'] ?: '-') . "</td>";
            echo "<td>$ativo</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($habito['data_criacao'])) . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao buscar hábitos:</strong> " . $e->getMessage() . "</p>";
}

// Verificar controles diários
echo "<h3>📅 Controles Diários (rotina_controle_diario):</h3>";

try {
    $stmt = $pdo->prepare("
        SELECT rcd.id, rcd.id_rotina_fixa, rcd.data_execucao, rcd.status, rcd.horario_execucao,
               rf.nome as nome_rotina
        FROM rotina_controle_diario rcd
        LEFT JOIN rotinas_fixas rf ON rcd.id_rotina_fixa = rf.id
        WHERE rcd.id_usuario = ? 
        ORDER BY rcd.data_execucao DESC, rcd.id DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $controles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($controles)) {
        echo "<p>❌ Nenhum controle diário encontrado.</p>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr><th>ID</th><th>Rotina ID</th><th>Nome</th><th>Data</th><th>Status</th><th>Horário</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($controles as $controle) {
            $statusClass = '';
            switch ($controle['status']) {
                case 'concluido':
                    $statusClass = 'text-success';
                    break;
                case 'pendente':
                    $statusClass = 'text-warning';
                    break;
                case 'pulado':
                    $statusClass = 'text-danger';
                    break;
            }
            
            echo "<tr>";
            echo "<td>" . $controle['id'] . "</td>";
            echo "<td>" . $controle['id_rotina_fixa'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($controle['nome_rotina'] ?: 'N/A') . "</strong></td>";
            echo "<td>" . $controle['data_execucao'] . "</td>";
            echo "<td class='$statusClass'><strong>" . ucfirst($controle['status']) . "</strong></td>";
            echo "<td>" . ($controle['horario_execucao'] ?: '-') . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao buscar controles:</strong> " . $e->getMessage() . "</p>";
}

// Verificar se há hábitos de teste
echo "<h3>🔍 Verificação de Hábitos de Teste:</h3>";

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN nome LIKE '%teste%' OR nome LIKE '%Teste%' THEN 1 ELSE 0 END) as com_teste
        FROM rotinas_fixas 
        WHERE id_usuario = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<div class='alert alert-info'>";
    echo "<h5>📊 Estatísticas:</h5>";
    echo "<ul>";
    echo "<li><strong>Total de hábitos:</strong> " . $stats['total'] . "</li>";
    echo "<li><strong>Hábitos com 'teste':</strong> " . $stats['com_teste'] . "</li>";
    echo "</ul>";
    echo "</div>";
    
    if ($stats['com_teste'] > 0) {
        echo "<div class='alert alert-warning'>";
        echo "<h5>⚠️ Hábitos de Teste Encontrados:</h5>";
        
        $stmt = $pdo->prepare("
            SELECT id, nome, data_criacao
            FROM rotinas_fixas 
            WHERE id_usuario = ? AND (nome LIKE '%teste%' OR nome LIKE '%Teste%')
            ORDER BY data_criacao DESC
        ");
        $stmt->execute([$userId]);
        $testes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        foreach ($testes as $teste) {
            echo "<li><strong>" . htmlspecialchars($teste['nome']) . "</strong> (ID: " . $teste['id'] . ") - " . date('d/m/Y H:i', strtotime($teste['data_criacao'])) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao verificar testes:</strong> " . $e->getMessage() . "</p>";
}

// Verificar scripts que podem estar criando hábitos
echo "<h3>🔧 Verificação de Scripts Ativos:</h3>";

$scripts_suspeitos = [
    'teste_rotinas_simples.php',
    'verificar_rotinas_fixas.php',
    'modificar_rotina_fixa.php',
    'atualizar_tarefas_rotina_fixa.php'
];

echo "<div class='alert alert-warning'>";
echo "<h5>⚠️ Scripts que podem criar hábitos de teste:</h5>";
echo "<ul>";
foreach ($scripts_suspeitos as $script) {
    if (file_exists($script)) {
        $modificado = date('d/m/Y H:i', filemtime($script));
        echo "<li><strong>$script</strong> - Modificado em: $modificado</li>";
    }
}
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><a href='tarefas.php'>← Voltar para Tarefas</a></p>";
echo "<p><a href='verificar_rotinas_fixas.php'>← Verificar Rotinas Fixas</a></p>";
?>
