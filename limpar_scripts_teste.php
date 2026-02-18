<?php
// limpar_scripts_teste.php - Limpar scripts que podem criar hábitos de teste

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

echo "<h2>🧹 Limpeza de Scripts de Teste</h2>";
echo "<p><strong>Usuário ID:</strong> $userId</p>";

// Lista de scripts que podem criar hábitos de teste
$scripts_suspeitos = [
    'teste_rotinas_simples.php',
    'verificar_rotinas_fixas.php', 
    'modificar_rotina_fixa.php',
    'atualizar_tarefas_rotina_fixa.php',
    'teste_rotina_fixa_completo.php',
    'teste_rotina_usuarios.php',
    'criar_metas_exemplo.php',
    'criar_cursos_exemplo.php'
];

echo "<h3>🔍 Verificando Scripts Suspeitos:</h3>";

$scripts_encontrados = [];
foreach ($scripts_suspeitos as $script) {
    if (file_exists($script)) {
        $tamanho = filesize($script);
        $modificado = date('d/m/Y H:i', filemtime($script));
        $scripts_encontrados[] = [
            'nome' => $script,
            'tamanho' => $tamanho,
            'modificado' => $modificado
        ];
        
        echo "<div class='alert alert-warning'>";
        echo "<h5>⚠️ $script</h5>";
        echo "<p><strong>Tamanho:</strong> " . number_format($tamanho) . " bytes</p>";
        echo "<p><strong>Modificado em:</strong> $modificado</p>";
        echo "</div>";
    }
}

if (empty($scripts_encontrados)) {
    echo "<div class='alert alert-success'>";
    echo "<h5>✅ Nenhum script suspeito encontrado!</h5>";
    echo "<p>Todos os scripts de teste foram removidos ou não existem.</p>";
    echo "</div>";
} else {
    echo "<h3>🗑️ Opções de Limpeza:</h3>";
    
    echo "<div class='alert alert-info'>";
    echo "<h5>📋 Scripts Encontrados:</h5>";
    echo "<ul>";
    foreach ($scripts_encontrados as $script) {
        echo "<li><strong>{$script['nome']}</strong> - {$script['tamanho']} bytes - {$script['modificado']}</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // Verificar se o usuário quer remover os scripts
    if (isset($_POST['remover_scripts'])) {
        echo "<h3>🗑️ Removendo Scripts:</h3>";
        
        $removidos = 0;
        $erros = 0;
        
        foreach ($scripts_encontrados as $script) {
            try {
                if (unlink($script['nome'])) {
                    echo "<p>✅ <strong>{$script['nome']}</strong> removido com sucesso</p>";
                    $removidos++;
                } else {
                    echo "<p>❌ <strong>{$script['nome']}</strong> não pôde ser removido</p>";
                    $erros++;
                }
            } catch (Exception $e) {
                echo "<p>❌ <strong>{$script['nome']}</strong> erro: " . $e->getMessage() . "</p>";
                $erros++;
            }
        }
        
        echo "<div class='alert alert-info'>";
        echo "<h5>📊 Resultado da Limpeza:</h5>";
        echo "<ul>";
        echo "<li><strong>Removidos:</strong> $removidos</li>";
        echo "<li><strong>Erros:</strong> $erros</li>";
        echo "<li><strong>Total:</strong> " . count($scripts_encontrados) . "</li>";
        echo "</ul>";
        echo "</div>";
        
    } else {
        echo "<form method='POST'>";
        echo "<div class='alert alert-warning'>";
        echo "<h5>⚠️ Atenção!</h5>";
        echo "<p>Estes scripts podem estar criando hábitos de teste automaticamente.</p>";
        echo "<p>Deseja removê-los para evitar problemas futuros?</p>";
        echo "</div>";
        echo "<button type='submit' name='remover_scripts' class='btn btn-danger'>";
        echo "<i class='bi bi-trash me-2'></i>Remover Scripts Suspeitos";
        echo "</button>";
        echo "</form>";
    }
}

// Verificar hábitos atuais do usuário
echo "<h3>📋 Hábitos Atuais do Usuário:</h3>";

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
        echo "<p>❌ Nenhum hábito encontrado.</p>";
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

// Verificar se há hábitos de teste no banco
echo "<h3>🔍 Verificação de Hábitos de Teste no Banco:</h3>";

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, data_criacao
        FROM rotinas_fixas 
        WHERE id_usuario = ? AND (nome LIKE '%teste%' OR nome LIKE '%Teste%' OR nome LIKE '%TESTE%')
        ORDER BY data_criacao DESC
    ");
    $stmt->execute([$userId]);
    $testes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($testes)) {
        echo "<div class='alert alert-success'>";
        echo "<h5>✅ Nenhum hábito de teste encontrado!</h5>";
        echo "<p>Seus hábitos estão limpos e não há dados de teste.</p>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>";
        echo "<h5>⚠️ Hábitos de Teste Encontrados:</h5>";
        echo "<ul>";
        foreach ($testes as $teste) {
            echo "<li><strong>" . htmlspecialchars($teste['nome']) . "</strong> (ID: " . $teste['id'] . ") - " . date('d/m/Y H:i', strtotime($teste['data_criacao'])) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
        
        if (isset($_POST['remover_testes'])) {
            echo "<h4>🗑️ Removendo Hábitos de Teste:</h4>";
            
            $removidos = 0;
            foreach ($testes as $teste) {
                try {
                    // Remover controles diários primeiro
                    $stmt = $pdo->prepare("DELETE FROM rotina_controle_diario WHERE id_rotina_fixa = ?");
                    $stmt->execute([$teste['id']]);
                    
                    // Remover rotina fixa
                    $stmt = $pdo->prepare("DELETE FROM rotinas_fixas WHERE id = ?");
                    $stmt->execute([$teste['id']]);
                    
                    echo "<p>✅ <strong>" . htmlspecialchars($teste['nome']) . "</strong> removido</p>";
                    $removidos++;
                } catch (PDOException $e) {
                    echo "<p>❌ Erro ao remover <strong>" . htmlspecialchars($teste['nome']) . "</strong>: " . $e->getMessage() . "</p>";
                }
            }
            
            echo "<div class='alert alert-success'>";
            echo "<h5>✅ Limpeza Concluída!</h5>";
            echo "<p><strong>$removidos</strong> hábitos de teste removidos.</p>";
            echo "</div>";
        } else {
            echo "<form method='POST'>";
            echo "<button type='submit' name='remover_testes' class='btn btn-danger'>";
            echo "<i class='bi bi-trash me-2'></i>Remover Hábitos de Teste";
            echo "</button>";
            echo "</form>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p>❌ <strong>Erro ao verificar testes:</strong> " . $e->getMessage() . "</p>";
}

// Recomendações finais
echo "<h3>💡 Recomendações:</h3>";

echo "<div class='alert alert-info'>";
echo "<h5>📋 Para Evitar Problemas Futuros:</h5>";
echo "<ul>";
echo "<li><strong>Não execute</strong> scripts de teste em produção</li>";
echo "<li><strong>Remova</strong> scripts que criam dados de exemplo</li>";
echo "<li><strong>Use apenas</strong> a interface normal para criar hábitos</li>";
echo "<li><strong>Monitore</strong> regularmente seus hábitos</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><a href='tarefas.php' class='btn btn-primary'>← Voltar para Tarefas</a></p>";
echo "<p><a href='verificar_habitos_usuario.php' class='btn btn-secondary'>← Verificar Hábitos</a></p>";
?>
