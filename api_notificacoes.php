<?php
// api_notificacoes.php - API de histórico de notificações in-app
header('Content-Type: application/json');
require_once 'includes/db_connect.php';

session_start();

$userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0;

if (!$userId || !$pdo) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Auto-criar tabelas necessárias
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificacoes_historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        mensagem TEXT NOT NULL,
        url VARCHAR(500) DEFAULT 'dashboard.php',
        tipo VARCHAR(50) DEFAULT 'info',
        lida TINYINT(1) DEFAULT 0,
        enviada_push TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        INDEX idx_lida (lida)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { /* já existe */ }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Para POST, ler JSON body
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $action ?: ($input['action'] ?? '');
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($action === 'count') {
        // Contar não lidas
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes_historico WHERE user_id = ? AND lida = 0");
            $stmt->execute([$userId]);
            $count = (int)$stmt->fetchColumn();
            echo json_encode(['success' => true, 'unread' => $count]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'unread' => 0]);
        }
        exit;
    }

    if ($action === 'list') {
        // Listar notificações
        $limit = min((int)($_GET['limit'] ?? 20), 50);
        $offset = (int)($_GET['offset'] ?? 0);
        try {
            $stmt = $pdo->prepare("
                SELECT id, titulo, mensagem, url, tipo, lida, enviada_push, created_at
                FROM notificacoes_historico
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM notificacoes_historico WHERE user_id = ?");
            $stmtTotal->execute([$userId]);
            $total = (int)$stmtTotal->fetchColumn();

            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'total' => $total
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'notifications' => [], 'total' => 0]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

// ─── POST ─────────────────────────────────────────────────────────────────────
if ($method === 'POST') {

    if ($action === 'mark_read') {
        // Marcar todas como lidas
        $id = $input['id'] ?? null;
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE notificacoes_historico SET lida = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE notificacoes_historico SET lida = 1 WHERE user_id = ? AND lida = 0");
                $stmt->execute([$userId]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao marcar como lida']);
        }
        exit;
    }

    if ($action === 'add') {
        // Adicionar notificação manualmente (uso interno)
        $titulo   = trim($input['titulo'] ?? '');
        $mensagem = trim($input['mensagem'] ?? '');
        $url      = trim($input['url'] ?? 'dashboard.php');
        $tipo     = trim($input['tipo'] ?? 'info');

        if (!$titulo || !$mensagem) {
            echo json_encode(['success' => false, 'message' => 'Título e mensagem são obrigatórios']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO notificacoes_historico (user_id, titulo, mensagem, url, tipo)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $titulo, $mensagem, $url, $tipo]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar notificação']);
        }
        exit;
    }

    if ($action === 'delete') {
        // Apagar notificação
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM notificacoes_historico WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao apagar notificação']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

// ─── DELETE ───────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    // Limpar todas as notificações lidas
    try {
        $stmt = $pdo->prepare("DELETE FROM notificacoes_historico WHERE user_id = ? AND lida = 1");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao limpar notificações']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método não suportado']);
