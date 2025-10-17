
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

        // Redirecionar direto para tarefas_otimizado.php
        header('Location: tarefas_otimizado.php?novo=1');
        exit;
        
    } catch (PDOException $e) {
        $erro = $e->getMessage();
    }
}

// Se acessar com novo=1, mostrar mensagem de sucesso
$mostrar_sucesso = isset($_GET['novo']);
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-voltar:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }
        .alert {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #6bcf7f;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1><i class="bi bi-plus-circle"></i> Inserir Tarefa de Teste</h1>
        
        <?php if ($mostrar_sucesso): ?>
            <div class="alert">
                ✅ <strong>Tarefa criada com sucesso!</strong><br>
                A tarefa apareceu na página de tarefas otimizada.
            </div>
        <?php endif; ?>
        
        <div class="description">
            ℹ️ Vai ser criada uma tarefa de teste com:<br>
            • <strong>Título:</strong> 🎯 Tarefa de Teste<br>
            • <strong>Prioridade:</strong> Alta<br>
            • <strong>Data:</strong> +3 dias<br>
            • <strong>Tempo:</strong> 2 horas<br>
            <br>
            Você será redirecionado para a página de tarefas otimizada automaticamente.
        </div>

        <a href="?criar=1" class="btn-criar">
            <i class="bi bi-plus"></i> Criar Tarefa de Teste
        </a>
        
        <a href="tarefas_otimizado.php" class="btn-voltar">
            <i class="bi bi-arrow-right"></i> Ir para Tarefas
        </a>
    </div>
</body>
</html>
