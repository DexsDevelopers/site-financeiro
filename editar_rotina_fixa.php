<?php
session_start();
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;

if (!$userId) {
    header('Location: index.php');
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$rotinaId = null;
$rotina = null;
$erro = null;
$sucesso = null;

// ===== OBTER ID DA ROTINA (GET ou POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rotinaId = (int)($_GET['id'] ?? 0);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rotinaId = (int)($_POST['id'] ?? 0);
}

if (!$rotinaId) {
    header('Location: tarefas.php?error=id_invalido');
    exit;
}

// ===== BUSCAR ROTINA =====
$stmt = $pdo->prepare("SELECT * FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
$stmt->execute([$rotinaId, $userId]);
$rotina = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rotina) {
    header('Location: tarefas.php?error=rotina_nao_encontrada');
    exit;
}

// ===== PROCESSAR POST (FORMULÁRIO HTML OU JSON) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // JSON POST (API)
    if (strpos($contentType, 'application/json') !== false) {
        header('Content-Type: application/json');

        
$input = json_decode(file_get_contents('php://input'), true);        $nome = trim($input['nome'] ?? '');        $horarioSugerido = $input['horario'] ?? null;        $descricao = trim($input['descricao'] ?? '');        $prioridade = $input['prioridade'] ?? 'Média';

        if (!$nome) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
            exit;        }
        if (empty($horarioSugerido) || $horarioSugerido === '00:00') {
            $horarioSugerido = null;        }
        try {
            $stmt = $pdo->prepare("
                UPDATE rotinas_fixas 
                SET nome = ?, horario_sugerido = ?, descricao = ?, prioridade = ?
                WHERE id = ? AND id_usuario = ?
            ");
            $stmt->execute([$nome, $horarioSugerido, $descricao, $prioridade, $rotinaId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Rotina atualizada com sucesso!']);
            exit;
        }
        catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
            exit;
        }
    }
    // Form POST (Formulário HTML)
    else {
        $nome = trim($_POST['nome'] ?? '');
        $horario = $_POST['horario'] ?? null;
        $descricao = trim($_POST['descricao'] ?? '');
        $prioridade = $_POST['prioridade'] ?? 'Média';

        if (!$nome) {
            $erro = 'Nome é obrigatório';
        }
        else {
            try {
                $stmt = $pdo->prepare("
        UPDATE rotinas_fixas 
        SET nome = ?, horario_sugerido = ?, descricao = ?, prioridade = ?
        WHERE id = ? AND id_usuario = ?
    ");
                $stmt->execute([$nome, $horario ?: null, $descricao, $prioridade, $rotinaId, $userId]);

                // Recarregar dados atualizados
                $stmt = $pdo->prepare("SELECT * FROM rotinas_fixas WHERE id = ? AND id_usuario = ?");
                $stmt->execute([$rotinaId, $userId]);
                $rotina = $stmt->fetch(PDO::FETCH_ASSOC);

                $sucesso = 'Rotina atualizada com sucesso!';
            }
            catch (Exception $e) {
                $erro = 'Erro ao atualizar: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Rotina - Painel Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #dc3545;
            --bg-dark: #0a0a0a;
            --bg-card: #141414;
        }
        body { 
            background: var(--bg-dark); 
            color: #fff; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
        }
        .container { 
            max-width: 500px; 
            margin-top: 40px; 
        }
        .card { 
            background: var(--bg-card); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
        }
        .card-header {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .form-control, .form-select { 
            background: #0a0a0a; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            color: #fff; 
        }
        .form-control:focus, .form-select:focus { 
            background: #0a0a0a; 
            border-color: var(--primary); 
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
        .btn-primary { 
            background: var(--primary); 
            border: none; 
        }
        .btn-primary:hover { 
            background: #c4080f; 
        }
        .alert {
            border: none;
            border-radius: 6px;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .alert-danger {
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Editar Rotina Fixa</h5>
            </div>
            <div class="card-body">
                <?php if ($sucesso): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $sucesso; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php
endif; ?>

                <?php if ($erro): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $erro; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php
endif; ?>

                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $rotinaId; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome da Rotina <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control" 
                               value="<?php echo htmlspecialchars($rotina['nome']); ?>" 
                               required autofocus>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Horário Sugerido</label>
                            <input type="time" name="horario" class="form-control" 
                                   value="<?php echo($rotina['horario_sugerido']) ? substr($rotina['horario_sugerido'], 0, 5) : ''; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Prioridade</label>
                            <select name="prioridade" class="form-select">
                                <option value="Baixa" <?php echo($rotina['prioridade'] == 'Baixa') ? 'selected' : ''; ?>>Baixa</option>
                                <option value="Média" <?php echo($rotina['prioridade'] == 'Média') ? 'selected' : ''; ?>>Média</option>
                                <option value="Alta" <?php echo($rotina['prioridade'] == 'Alta') ? 'selected' : ''; ?>>Alta</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descrição (opcional)</label>
                        <textarea name="descricao" class="form-control" rows="4"><?php echo htmlspecialchars($rotina['descricao'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="tarefas.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>