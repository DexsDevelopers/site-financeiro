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
        $diasSemana = isset($_POST['dias_semana']) ? implode(',', $_POST['dias_semana']) : null;

        if (!$nome) {
            $erro = 'Nome é obrigatório';
        }
        else {
            try {
                $stmt = $pdo->prepare("
        UPDATE rotinas_fixas 
        SET nome = ?, horario_sugerido = ?, descricao = ?, prioridade = ?, dias_semana = ?
        WHERE id = ? AND id_usuario = ?
    ");
                $stmt->execute([$nome, $horario ?: null, $descricao, $prioridade, $diasSemana, $rotinaId, $userId]);

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
<?php
$page_title = "Editar Rotina Fixa - Orion";
require_once 'templates/header.php';

// CSS Específico para esta página com cache buster
echo '<link rel="stylesheet" href="' . asset('tarefas.css') . '">';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-premium border-0">
                <div class="card-header bg-transparent border-bottom border-white-5 opacity-75">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i> Editar Rotina Fixa</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $sucesso; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $erro; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $rotinaId; ?>">
                        
                        <div class="mb-4">
                            <label class="form-label">Nome da Rotina</label>
                            <input type="text" name="nome" class="form-control form-control-lg" 
                                   value="<?php echo htmlspecialchars($rotina['nome']); ?>" 
                                   required autofocus>
                        </div>

                        <div class="row mb-4">
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

                        <div class="mb-4">
                            <label class="form-label">Dias da Semana</label>
                            <div class="d-flex flex-wrap gap-2 dias-selecao">
                                <?php 
                                $diasNome = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                                $diasSelecionados = !empty($rotina['dias_semana']) ? explode(',', $rotina['dias_semana']) : [];
                                for($i=1; $i<=7; $i++): 
                                    $checked = in_array($i, $diasSelecionados) ? 'checked' : '';
                                ?>
                                <div class="dia-item flex-fill">
                                    <input type="checkbox" class="btn-check" name="dias_semana[]" id="edit_dia_<?= $i ?>" value="<?= $i ?>" <?= $checked ?>>
                                    <label class="btn btn-outline-light w-100 py-2 px-0" for="edit_dia_<?= $i ?>"><?= $diasNome[$i-1] ?></label>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <small class="text-white-50 mt-2 d-block">Se nenhum for selecionado, aparecerá todos os dias.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Descrição (opcional)</label>
                            <textarea name="descricao" class="form-control" rows="3"><?php echo htmlspecialchars($rotina['descricao'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3">
                            <a href="tarefas.php" class="btn btn-outline-light px-4">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </a>
                            <button type="submit" class="btn btn-primary px-5">
                                <i class="bi bi-save"></i> Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>
