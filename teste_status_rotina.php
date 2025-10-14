<?php
session_start();
$_SESSION['user_id'] = 1;

require_once 'includes/db_connect.php';

echo "<h1>Teste Status Rotina</h1>";

// Verificar rotinas fixas
try {
    $stmt = $pdo->prepare("
        SELECT rf.*, 
               rcd.status as status_hoje,
               rcd.horario_execucao,
               rcd.observacoes
        FROM rotinas_fixas rf
        LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa 
            AND rcd.id_usuario = rf.id_usuario 
            AND rcd.data_execucao = ?
        WHERE rf.id_usuario = ? AND rf.ativo = TRUE
        ORDER BY rf.ordem, rf.horario_sugerido
    ");
    $stmt->execute([date('Y-m-d'), 1]);
    $rotinas = $stmt->fetchAll();
    
    echo "<h2>Rotinas Fixas:</h2>";
    if (empty($rotinas)) {
        echo "<p>❌ Nenhuma rotina fixa encontrada</p>";
    } else {
        foreach ($rotinas as $rotina) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            echo "<h3>" . htmlspecialchars($rotina['nome']) . "</h3>";
            echo "<p><strong>Status hoje:</strong> " . ($rotina['status_hoje'] ?? 'pendente') . "</p>";
            echo "<p><strong>Horário execução:</strong> " . ($rotina['horario_execucao'] ?? 'não executado') . "</p>";
            echo "<p><strong>Observações:</strong> " . ($rotina['observacoes'] ?? 'nenhuma') . "</p>";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro ao buscar rotinas: " . $e->getMessage() . "</p>";
}

// Testar atualização de status
echo "<h2>Teste de Atualização:</h2>";
if (isset($_POST['testar_status'])) {
    $rotinaId = $_POST['rotina_id'];
    $status = $_POST['status'];
    
    try {
        $dataHoje = date("Y-m-d");
        $horario = $status === "concluido" ? date("H:i:s") : null;
        
        $stmt = $pdo->prepare("
            UPDATE rotina_controle_diario 
            SET status = ?, horario_execucao = ?
            WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
        ");
        
        $resultado = $stmt->execute([$status, $horario, 1, $rotinaId, $dataHoje]);
        
        if ($resultado) {
            echo "<p>✅ Status atualizado com sucesso!</p>";
        } else {
            echo "<p>❌ Erro ao atualizar status</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erro na atualização: " . $e->getMessage() . "</p>";
    }
}

// Formulário de teste
if (!empty($rotinas)) {
    echo "<h2>Testar Atualização Manual:</h2>";
    echo "<form method='POST'>";
    echo "<select name='rotina_id'>";
    foreach ($rotinas as $rotina) {
        echo "<option value='" . $rotina['id'] . "'>" . htmlspecialchars($rotina['nome']) . "</option>";
    }
    echo "</select>";
    echo "<select name='status'>";
    echo "<option value='pendente'>Pendente</option>";
    echo "<option value='concluido'>Concluído</option>";
    echo "<option value='pulado'>Pulado</option>";
    echo "</select>";
    echo "<button type='submit' name='testar_status'>Testar</button>";
    echo "</form>";
}
?>
