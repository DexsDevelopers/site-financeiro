
<?php
session_start();
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? null;
$mensagem = '';
$tipo_mensagem = '';

if (!$userId) {
    $tipo_mensagem = 'erro';
    $mensagem = '❌ Você não está logado!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    try {
        $tarefas_teste = [
            ['📝 Revisar documentação', 'Revisar e atualizar a documentação do projeto', 'Alta', 3, 120],
            ['🐛 Corrigir bugs da aplicação', 'Identificar e corrigir bugs reportados pelos usuários', 'Alta', 5, 240],
            ['📊 Preparar relatório mensal', 'Compilar dados e preparar relatório de progresso', 'Média', 7, 180],
            ['💬 Responder emails pendentes', 'Responder todos os emails da caixa de entrada', 'Média', 1, 60],
            ['🎨 Atualizar design da interface', 'Implementar novo design conforme aprovado pelo time', 'Baixa', 10, 300],
            ['📚 Estudar nova tecnologia', 'Aprender sobre a nova stack que vamos usar no próximo projeto', 'Baixa', 14, 420]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO tarefas (id_usuario, titulo, descricao, prioridade, data_limite, tempo_estimado, status, data_criacao)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente', NOW())
        ");

        foreach ($tarefas_teste as $tarefa) {
            $data_limite = date('Y-m-d', strtotime('+' . $tarefa[3] . ' days'));
            $stmt->execute([$userId, $tarefa[0], $tarefa[1], $tarefa[2], $data_limite, $tarefa[4]]);
        }

        $tipo_mensagem = 'sucesso';
        $mensagem = '✨ 6 tarefas de teste criadas com sucesso!';
    } catch (PDOException $e) {
        $tipo_mensagem = 'erro';
        $mensagem = '❌ Erro ao criar tarefas: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Tarefas de Teste</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0505 100%);
            color: white;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .container {
            max-width: 500px;
            margin: 50px auto;
        }

        .card {
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        h1 {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 15px;
        }

        .alert-sucesso {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #6bcf7f;
        }

        .alert-erro {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .info-box strong {
            color: #dc3545;
        }

        .tarefas-lista {
            background: rgba(255, 255, 255, 0.03);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .tarefas-lista ul {
            margin: 0;
            padding-left: 20px;
        }

        .tarefas-lista li {
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
        }

        .btn-action {
            width: 100%;
            padding: 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 10px;
        }

        .btn-action:hover {
            background: #c4080f;
            transform: translateY(-2px);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.8);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .link-back {
            display: inline-block;
            margin-top: 20px;
            color: #dc3545;
            text-decoration: none;
            font-weight: 600;
        }

        .link-back:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>
                <i class="bi bi-plus-circle"></i> Criar Tarefas de Teste
            </h1>

            <?php if ($mensagem): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>

            <?php if ($userId): ?>
                <div class="info-box">
                    ℹ️ <strong>Sobre as tarefas:</strong><br>
                    Serão criadas 6 tarefas de exemplo com diferentes prioridades, datas e tempos estimados para você visualizar como fica na página de tarefas otimizada.
                </div>

                <div class="tarefas-lista">
                    <strong>📋 Tarefas que serão criadas:</strong>
                    <ul>
                        <li><strong>🔴 Alta:</strong> 📝 Revisar documentação (2h)</li>
                        <li><strong>🔴 Alta:</strong> 🐛 Corrigir bugs (4h)</li>
                        <li><strong>🟡 Média:</strong> 📊 Relatório mensal (3h)</li>
                        <li><strong>🟡 Média:</strong> 💬 Responder emails (1h)</li>
                        <li><strong>🟢 Baixa:</strong> 🎨 Atualizar design (5h)</li>
                        <li><strong>🟢 Baixa:</strong> 📚 Estudar tecnologia (7h)</li>
                    </ul>
                </div>

                <form method="POST">
                    <button type="submit" class="btn-action">
                        <i class="bi bi-plus"></i> Criar Tarefas de Teste
                    </button>
                </form>

                <a href="tarefas_otimizado.php" class="btn btn-secondary btn-action">
                    <i class="bi bi-arrow-right"></i> Ver Tarefas
                </a>
            <?php else: ?>
                <p style="text-align: center;">
                    <a href="index.php" class="btn btn-primary">Faça login</a>
                </p>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 20px;">
                <a href="tarefas_otimizado.php" class="link-back">
                    ← Voltar para tarefas
                </a>
            </div>
        </div>
    </div>
</body>
</html>
