
<?php
session_start();
require_once 'includes/db_connect.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['criar'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tarefas (id_usuario, titulo, descricao, prioridade, data_limite, tempo_estimado, status, data_criacao)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente', NOW())
        ");

        $stmt->execute([
            $userId,
            '🎯 Tarefa de Teste - Página Otimizada',
            'Esta é uma tarefa de teste para visualizar como fica na página de tarefas otimizada. Você pode marcar como concluída, editar ou deletar.',
            'Alta',
            date('Y-m-d', strtotime('+3 days')),
            120 // 2 horas
        ]);

        $tarefaId = $pdo->lastInsertId();
        
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>✅ Tarefa Criada</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <style>
                body {
                    background: linear-gradient(135deg, #0a0a0a 0%, #1a0505 100%);
                    color: white;
                    min-height: 100vh;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .card {
                    background: rgba(20, 20, 20, 0.9);
                    border: 1px solid rgba(40, 167, 69, 0.5);
                    border-radius: 15px;
                    padding: 40px;
                    text-align: center;
                    max-width: 500px;
                }
                .icon {
                    font-size: 60px;
                    margin-bottom: 20px;
                    animation: bounce 0.6s;
                }
                @keyframes bounce {
                    0%, 100% { transform: translateY(0); }
                    50% { transform: translateY(-10px); }
                }
                .btn-group {
                    display: flex;
                    gap: 10px;
                    margin-top: 30px;
                }
                .btn {
                    flex: 1;
                    padding: 12px;
                    border: none;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .btn-primary {
                    background: #28a745;
                    color: white;
                }
                .btn-primary:hover {
                    background: #20c997;
                    transform: translateY(-2px);
                }
                .btn-secondary {
                    background: transparent;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    color: rgba(255, 255, 255, 0.8);
                }
                .btn-secondary:hover {
                    background: rgba(255, 255, 255, 0.05);
                }
                .info-text {
                    background: rgba(40, 167, 69, 0.1);
                    border: 1px solid rgba(40, 167, 69, 0.2);
                    padding: 15px;
                    border-radius: 8px;
                    margin-top: 20px;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">✅</div>
                <h2>Tarefa Criada com Sucesso!</h2>
                <p style="color: rgba(255, 255, 255, 0.7); margin: 15px 0;">
                    ID: <strong>#<?php echo $tarefaId; ?></strong>
                </p>
                <div class="info-text">
                    📋 <strong>Tarefa de Teste</strong><br>
                    Prioridade: <strong style="color: #ff6b6b;">Alta</strong><br>
                    Data: <strong><?php echo date('d/m', strtotime('+3 days')); ?></strong><br>
                    Tempo: <strong>2 horas</strong>
                </div>
                <div class="btn-group">
                    <button onclick="window.location.href='tarefas_otimizado.php'" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Ver Tarefas
                    </button>
                    <button onclick="window.location.href='criar_tarefas_teste_web.php'" class="btn btn-secondary">
                        <i class="bi bi-plus"></i> Criar Mais
                    </button>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    } catch (PDOException $e) {
        $erro = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserir Tarefa de Teste</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0505 100%);
            color: white;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        h1 {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 30px;
        }
        .description {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.6;
        }
        .btn-criar {
            width: 100%;
            padding: 14px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 10px;
        }
        .btn-criar:hover {
            background: #c4080f;
            transform: translateY(-2px);
        }
        .btn-voltar {
            width: 100%;
            padding: 14px;
            background: transparent;
            color: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-voltar:hover {
            background: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>
    <div class="card">
        <h1><i class="bi bi-plus-circle"></i> Inserir Tarefa de Teste</h1>
        
        <div class="description">
            ℹ️ Vai ser criada uma tarefa de teste com:<br>
            • <strong>Título:</strong> 🎯 Tarefa de Teste<br>
            • <strong>Prioridade:</strong> Alta<br>
            • <strong>Data:</strong> +3 dias<br>
            • <strong>Tempo:</strong> 2 horas<br>
            <br>
            Você poderá ver na página de tarefas otimizada.
        </div>

        <a href="?criar=1" class="btn-criar">
            <i class="bi bi-plus"></i> Criar Tarefa de Teste
        </a>
        
        <a href="tarefas_otimizado.php" class="btn-voltar">
            ← Voltar para Tarefas
        </a>
    </div>
</body>
</html>
