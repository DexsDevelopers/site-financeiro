<?php
require_once 'includes/db_connect.php';

try {
    $pdo->beginTransaction();
    
    // Renomear categorias puramente numéricas para 'Geral'
    // Fazemos isso apenas se não houver conflito de nome para o mesmo usuário
    $stmt = $pdo->query("SELECT id, nome, id_usuario, tipo FROM categorias WHERE nome REGEXP '^[0-9]+$'");
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed = 0;
    foreach ($cats as $cat) {
        $newName = "Geral";
        
        // Verifica se o usuário já tem uma categoria 'Geral'
        $stmtCheck = $pdo->prepare("SELECT id FROM categorias WHERE id_usuario = ? AND nome = ? AND tipo = ?");
        $stmtCheck->execute([$cat['id_usuario'], $newName, $cat['tipo']]);
        $exists = $stmtCheck->fetchColumn();
        
        if ($exists) {
            // Se já existe 'Geral', movemos as transações da categoria numérica para a 'Geral' existente e deletamos a numérica
            $stmtUpdateTrans = $pdo->prepare("UPDATE transacoes SET id_categoria = ? WHERE id_categoria = ?");
            $stmtUpdateTrans->execute([$exists, $cat['id']]);
            
            $stmtDelete = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
            $stmtDelete->execute([$cat['id']]);
            $fixed++;
        } else {
            // Se não existe, apenas renomeia
            $stmtUpdateName = $pdo->prepare("UPDATE categorias SET nome = ? WHERE id = ?");
            $stmtUpdateName->execute([$newName, $cat['id']]);
            $fixed++;
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Categorias corrigidas: $fixed"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
