
<?php
require_once 'templates/header.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Tarefa - Painel Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #dc3545;
            --bg-dark: #0a0a0a;
            --bg-card: #141414;
            --border: rgba(255, 255, 255, 0.08);
            --text: #ffffff;
            --text-muted: #b0b0b0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 14px;
            min-height: 100vh;
        }

        .container-form {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 700;
        }

        .header a {
            font-size: 20px;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .header a:hover {
            color: var(--text);
        }

        .form-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 25px;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            width: 100%;
            font-family: inherit;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
            color: var(--text);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        select.form-control {
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
        }

        .btn-submit:hover {
            background: #c4080f;
            transform: translateY(-2px);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-cancel {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .form-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .loading {
            display: none;
        }

        .btn-submit.carregando {
            opacity: 0.7;
            pointer-events: none;
        }

        @media (max-width: 600px) {
            .container-form {
                margin: 20px auto;
                padding: 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-form">
        <!-- Header -->
        <div class="header">
            <a href="tarefas_otimizado.php" title="Voltar"><i class="bi bi-arrow-left"></i></a>
            <span><i class="bi bi-plus-circle"></i> Nova Tarefa</span>
        </div>

        <!-- Formulário -->
        <form class="form-card" id="formTarefa" method="POST" action="adicionar_tarefa.php">
            <!-- Título -->
            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-card-heading"></i> Título da Tarefa *
                </label>
                <input type="text" name="titulo" class="form-control" placeholder="Ex: Completar relatório" required maxlength="255">
            </div>

            <!-- Descrição -->
            <div class="form-group">
                <label class="form-label">
                    <i class="bi bi-file-text"></i> Descrição
                </label>
                <textarea name="descricao" class="form-control" placeholder="Detalhes da tarefa..."></textarea>
                <div class="form-hint">Opcional - Máximo 500 caracteres</div>
            </div>

            <!-- Row: Prioridade e Data Limite -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-exclamation-circle"></i> Prioridade *
                    </label>
                    <select name="prioridade" class="form-control" required>
                        <option value="Baixa">🟢 Baixa</option>
                        <option value="Média" selected>🟡 Média</option>
                        <option value="Alta">🔴 Alta</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-calendar"></i> Data Limite
                    </label>
                    <input type="date" name="data_limite" class="form-control">
                    <div class="form-hint">Opcional</div>
                </div>
            </div>

            <!-- Row: Tempo Estimado -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-clock"></i> Horas
                    </label>
                    <input type="number" name="tempo_horas" class="form-control" min="0" max="23" value="0">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-hourglass"></i> Minutos
                    </label>
                    <input type="number" name="tempo_minutos" class="form-control" min="0" max="59" value="0">
                </div>
            </div>

            <!-- Ações -->
            <div class="form-actions">
                <button type="button" class="btn btn-cancel" onclick="window.history.back()">
                    <i class="bi bi-x"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-submit">
                    <i class="bi bi-check2"></i> Criar Tarefa
                </button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('formTarefa').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = this.querySelector('.btn-submit');
            btn.classList.add('carregando');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Criando...';

            const formData = new FormData(this);

            fetch('adicionar_tarefa.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Sucesso! Redirecionar
                    alert('✅ Tarefa criada com sucesso!');
                    window.location.href = 'tarefas_otimizado.php';
                } else {
                    // Erro
                    alert('❌ Erro: ' + (data.message || 'Falha ao criar tarefa'));
                    btn.classList.remove('carregando');
                    btn.innerHTML = '<i class="bi bi-check2"></i> Criar Tarefa';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao processar: ' + error);
                btn.classList.remove('carregando');
                btn.innerHTML = '<i class="bi bi-check2"></i> Criar Tarefa';
            });
        });
    </script>
</body>
</html>
