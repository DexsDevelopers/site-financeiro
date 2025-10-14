<?php
// atualizar_tarefas_simples.php - Atualizar tarefas.php para usar rotinas fixas

echo "<h1>🔄 ATUALIZANDO TAREFAS.PHP PARA ROTINAS FIXAS</h1>";
echo "<hr>";

// Ler o arquivo tarefas.php atual
$arquivo_tarefas = 'tarefas.php';
if (!file_exists($arquivo_tarefas)) {
    echo "❌ <strong>Arquivo tarefas.php não encontrado!</strong><br>";
    exit;
}

$conteudo_atual = file_get_contents($arquivo_tarefas);
echo "✅ <strong>Arquivo tarefas.php carregado</strong><br>";

// Backup do arquivo original
$backup_file = 'tarefas_backup_' . date('Y-m-d_H-i-s') . '.php';
file_put_contents($backup_file, $conteudo_atual);
echo "💾 <strong>Backup criado:</strong> $backup_file<br><br>";

// Nova lógica para rotinas fixas (mais simples)
$nova_logica_rotina = '
// ===== ROTINA DIÁRIA FIXA INTEGRADA =====
$dataHoje = date("Y-m-d");

// Buscar rotinas fixas do usuário
$rotinasFixas = [];
$progressoRotina = 0;
try {
    // Buscar rotinas fixas ativas com controle diário
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
    $stmt->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmt->fetchAll();
    
    // Se não há controle para hoje, criar automaticamente
    foreach ($rotinasFixas as $rotina) {
        if ($rotina["status_hoje"] === null) {
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, "pendente")
            ");
            $stmt->execute([$userId, $rotina["id"], $dataHoje]);
        }
    }
    
    // Buscar novamente com os controles criados
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
    $stmt->execute([$dataHoje, $userId]);
    $rotinasFixas = $stmt->fetchAll();
    
    // Calcular progresso
    $totalRotinas = count($rotinasFixas);
    $rotinasConcluidas = array_filter($rotinasFixas, function($r) { 
        return $r["status_hoje"] === "concluido"; 
    });
    $progressoRotina = $totalRotinas > 0 ? (count($rotinasConcluidas) / $totalRotinas) * 100 : 0;
    
} catch (PDOException $e) {
    $rotinasFixas = [];
    $progressoRotina = 0;
    error_log("Erro ao buscar rotinas fixas: " . $e->getMessage());
}
';

// Substituir a seção de rotina diária
$inicio_rotina = '// ===== ROTINA DIÁRIA INTEGRADA =====';
$fim_rotina = '} catch (PDOException $e) {
    $rotinasHoje = [];
    $progressoRotina = 0;
}';

// Encontrar a posição da seção
$pos_inicio = strpos($conteudo_atual, $inicio_rotina);
$pos_fim = strpos($conteudo_atual, $fim_rotina);

if ($pos_inicio !== false && $pos_fim !== false) {
    // Substituir a seção
    $conteudo_novo = substr_replace($conteudo_atual, $nova_logica_rotina, $pos_inicio, $pos_fim - $pos_inicio + strlen($fim_rotina));
    
    // Substituir também as variáveis no resto do arquivo
    $conteudo_novo = str_replace('$rotinasHoje', '$rotinasFixas', $conteudo_novo);
    
    echo "✅ <strong>Seção de rotina diária substituída</strong><br>";
} else {
    echo "⚠️ <strong>Seção de rotina diária não encontrada. Tentando substituição manual...</strong><br>";
    
    // Tentar encontrar padrões alternativos
    $padroes = [
        '/\/\/ ===== ROTINA DIÁRIA INTEGRADA =====.*?} catch \(PDOException \$e\) \{[^}]*\}/s',
        '/\$rotinasHoje = \[\];.*?} catch \(PDOException \$e\) \{[^}]*\}/s'
    ];
    
    $conteudo_novo = $conteudo_atual;
    foreach ($padroes as $padrao) {
        if (preg_match($padrao, $conteudo_atual)) {
            $conteudo_novo = preg_replace($padrao, $nova_logica_rotina, $conteudo_atual);
            $conteudo_novo = str_replace('$rotinasHoje', '$rotinasFixas', $conteudo_novo);
            echo "✅ <strong>Substituição por regex realizada</strong><br>";
            break;
        }
    }
    
    if ($conteudo_novo === $conteudo_atual) {
        echo "❌ <strong>Não foi possível localizar a seção de rotina diária</strong><br>";
        echo "<div style='background: #f8d7da; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
        echo "<h4>🔧 Solução manual:</h4>";
        echo "<p>Você precisará editar manualmente o arquivo tarefas.php</p>";
        echo "<p>Substitua a lógica de rotina diária pela nova lógica de rotinas fixas</p>";
        echo "</div>";
        exit;
    }
}

// Salvar o arquivo atualizado
if (file_put_contents($arquivo_tarefas, $conteudo_novo)) {
    echo "✅ <strong>Arquivo tarefas.php atualizado com sucesso!</strong><br>";
} else {
    echo "❌ <strong>Erro ao salvar o arquivo atualizado</strong><br>";
    exit;
}

// Criar arquivo de funções auxiliares
$funcoes_rotina_fixa = '<?php
// includes/rotina_fixa_functions.php - Funções para rotinas fixas

/**
 * Atualizar status de rotina fixa
 */
function atualizarStatusRotinaFixa($pdo, $userId, $rotinaId, $status, $observacoes = null) {
    try {
        $dataHoje = date("Y-m-d");
        $horario = $status === "concluido" ? date("H:i:s") : null;
        
        $stmt = $pdo->prepare("
            UPDATE rotina_controle_diario 
            SET status = ?, horario_execucao = ?, observacoes = ?
            WHERE id_usuario = ? AND id_rotina_fixa = ? AND data_execucao = ?
        ");
        
        return $stmt->execute([$status, $horario, $observacoes, $userId, $rotinaId, $dataHoje]);
    } catch (PDOException $e) {
        error_log("Erro ao atualizar rotina fixa: " . $e->getMessage());
        return false;
    }
}

/**
 * Adicionar nova rotina fixa
 */
function adicionarRotinaFixa($pdo, $userId, $nome, $horario = null, $ordem = 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rotinas_fixas (id_usuario, nome, horario_sugerido, ordem, ativo) 
            VALUES (?, ?, ?, ?, TRUE)
        ");
        
        if ($stmt->execute([$userId, $nome, $horario, $ordem])) {
            $idRotina = $pdo->lastInsertId();
            
            // Criar controle para hoje
            $dataHoje = date("Y-m-d");
            $stmt = $pdo->prepare("
                INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                VALUES (?, ?, ?, "pendente")
            ");
            $stmt->execute([$userId, $idRotina, $dataHoje]);
            
            return $idRotina;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Erro ao adicionar rotina fixa: " . $e->getMessage());
        return false;
    }
}

/**
 * Remover rotina fixa
 */
function removerRotinaFixa($pdo, $userId, $rotinaId) {
    try {
        // Desativar rotina fixa
        $stmt = $pdo->prepare("UPDATE rotinas_fixas SET ativo = FALSE WHERE id = ? AND id_usuario = ?");
        return $stmt->execute([$rotinaId, $userId]);
    } catch (PDOException $e) {
        error_log("Erro ao remover rotina fixa: " . $e->getMessage());
        return false;
    }
}
?>';

file_put_contents('includes/rotina_fixa_functions.php', $funcoes_rotina_functions);
echo "✅ <strong>Funções auxiliares criadas</strong><br>";

echo "<hr>";
echo "<h2>🎉 SISTEMA ATUALIZADO COM SUCESSO!</h2>";
echo "<div style='background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>✅ O que foi feito:</h4>";
echo "<ul>";
echo "<li><strong>Backup criado:</strong> $backup_file</li>";
echo "<li><strong>Lógica atualizada:</strong> Agora usa rotinas fixas</li>";
echo "<li><strong>Funções auxiliares:</strong> includes/rotina_fixa_functions.php</li>";
echo "<li><strong>Controle diário:</strong> Sistema controla execução por dia</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>🔄 Como funciona agora:</h4>";
echo "<ol>";
echo "<li><strong>Rotinas fixas:</strong> Lista permanente de hábitos</li>";
echo "<li><strong>Controle diário:</strong> Marca se fez hoje ou não</li>";
echo "<li><strong>Progresso:</strong> Mostra quantos hábitos foram cumpridos</li>";
echo "<li><strong>Flexibilidade:</strong> Pode pular, adiar ou marcar como concluído</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h4>📝 Próximos passos:</h4>";
echo "<ol>";
echo "<li><strong>Teste o sistema:</strong> Acesse tarefas.php</li>";
echo "<li><strong>Verifique as rotinas:</strong> Devem aparecer como lista fixa</li>";
echo "<li><strong>Teste as funcionalidades:</strong> Marcar como concluído, pular, etc.</li>";
echo "<li><strong>Se houver problemas:</strong> Restaure o backup e me avise</li>";
echo "</ol>";
echo "</div>";
?>
