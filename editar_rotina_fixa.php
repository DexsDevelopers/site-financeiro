<?php
session_start();
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$userId) {
    header('Location: index.php');
    exit;
}

// Se for GET, buscar dados e exibir formulário
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rotinaId = (int)($_GET['id'] ?? 0);
    
    if (!$rotinaId) {
        header('Location: tarefas.php?error=id_invalido');
        exit;
    }
    
    // Buscar rotina
    $stmt = $pdo->prepare("SELECT * FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$rotinaId, $userId]);
    $rotina = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rotina) {
        header('Location: tarefas.php?error=rotina_nao_encontrada');
        exit;
    }
    
    // Se houver dados POST, processar atualização
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim($_POST['nome'] ?? '');
        $horario = $_POST['horario'] ?? null;
        $descricao = trim($_POST['descricao'] ?? '');
        
        if (!$nome) {
            header('Location: tarefas.php?error=dados_invalidos');
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE rotinas_fixas 
                SET nome = ?, horario_sugerido = ?, descricao = ?, data_atualizacao = NOW()
                WHERE id = ? AND id_usuario = ?
            ");
            $stmt->execute([$nome, $horario ?: null, $descricao, $rotinaId, $userId]);
            
            header('Location: tarefas.php?success=rotina_atualizada');
            exit;
        } catch (Exception $e) {
            header('Location: tarefas.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }
    
    // ... rest of file ...
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Rotina - Painel Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #dc3545;
            --bg-dark: #0a0a0a;
            --bg-card: #141414;
        }
        body { background: var(--bg-dark); color: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .container { max-width: 500px; margin-top: 40px; }
        .card { background: var(--bg-card); border: 1px solid rgba(255, 255, 255, 0.1); }
        .form-control { background: #0a0a0a; border: 1px solid rgba(255, 255, 255, 0.1); color: #fff; }
        .form-control:focus { background: #0a0a0a; border-color: var(--primary); color: #fff; }
        .btn-primary { background: var(--primary); border: none; }
        .btn-primary:hover { background: #c4080f; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header" style="background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Editar Rotina</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Nome da Rotina</label>
                        <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($rotina['nome']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Horário Sugerido (opcional)</label>
                        <input type="time" name="horario" class="form-control" value="<?php echo $rotina['horario_sugerido'] ? substr($rotina['horario_sugerido'], 0, 5) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição (opcional)</label>
                        <textarea name="descricao" class="form-control" rows="3"><?php echo htmlspecialchars($rotina['descricao']); ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="tarefas.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html><?php
} else {
    // Se for POST com JSON, processar como API
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $rotinaId = $input['id'] ?? null;
    $nome = trim($input['nome'] ?? '');
    $horarioSugerido = $input['horario'] ?? null;
    $descricao = trim($input['descricao'] ?? '');
    
    if (!$rotinaId || !$nome) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos']);
        exit;
    }
    
    if (empty($horarioSugerido) || $horarioSugerido === '00:00') {
        $horarioSugerido = null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$rotinaId, $userId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Rotina não encontrada']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            UPDATE rotinas_fixas 
            SET nome = ?, horario_sugerido = ?, descricao = ?
            WHERE id = ? AND id_usuario = ?
        ");
        
        $stmt->execute([$nome, $horarioSugerido, $descricao, $rotinaId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Rotina atualizada com sucesso!']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
}
?>