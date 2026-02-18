<?php
// testar_alertas.php - Teste do sistema de alertas

session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['teste'])) {
    echo json_encode(['success' => false, 'message' => 'Parâmetro de teste inválido']);
    exit;
}

// Criar tabela de alertas se não existir
try {
    $sql_create_alertas = "CREATE TABLE IF NOT EXISTS alertas_inteligentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        tipo ENUM('urgente', 'info', 'sucesso', 'aviso') NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        mensagem TEXT NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        lido TINYINT(1) DEFAULT 0,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (id_usuario),
        INDEX idx_tipo (tipo),
        INDEX idx_data (data_criacao)
    )";
    $pdo->exec($sql_create_alertas);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar tabela: ' . $e->getMessage()]);
    exit;
}

// Limpar alertas de teste existentes primeiro
try {
    $stmt_limpar = $pdo->prepare("DELETE FROM alertas_inteligentes WHERE id_usuario = ? AND (
        titulo LIKE '%Gasto Alto Detectado%' OR
        titulo LIKE '%Padrão de Gasto Identificado%' OR
        titulo LIKE '%Meta de Economia Atingida%' OR
        titulo LIKE '%Saldo Baixo%' OR
        mensagem LIKE '%R$ 1.200,00%' OR
        mensagem LIKE '%Supermercado Extra%' OR
        mensagem LIKE '%finais de semana%' OR
        mensagem LIKE '%meta de economia%' OR
        mensagem LIKE '%iPhone%' OR
        mensagem LIKE '%saldo atual%'
    )");
    $stmt_limpar->execute([$userId]);
} catch (PDOException $e) {
    // Ignorar erro se a tabela não existir
}

// Criar alertas de teste
$alertas_teste = [
    [
        'tipo' => 'urgente',
        'titulo' => 'Gasto Alto Detectado',
        'mensagem' => 'Você fez uma compra de R$ 1.200,00 no Supermercado Extra. Este valor está acima do seu limite configurado.'
    ],
    [
        'tipo' => 'info',
        'titulo' => 'Padrão de Gasto Identificado',
        'mensagem' => 'Identificamos que você gasta mais aos finais de semana. Considere planejar melhor suas compras.'
    ],
    [
        'tipo' => 'sucesso',
        'titulo' => 'Meta de Economia Atingida',
        'mensagem' => 'Parabéns! Você atingiu 75% da sua meta de economia para o iPhone. Continue assim!'
    ],
    [
        'tipo' => 'aviso',
        'titulo' => 'Saldo Baixo',
        'mensagem' => 'Seu saldo atual está próximo do limite. Considere revisar seus gastos ou fazer uma transferência.'
    ]
];

try {
    $stmt = $pdo->prepare("INSERT INTO alertas_inteligentes (id_usuario, tipo, titulo, mensagem) VALUES (?, ?, ?, ?)");
    
    foreach ($alertas_teste as $alerta) {
        $stmt->execute([$userId, $alerta['tipo'], $alerta['titulo'], $alerta['mensagem']]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Alertas de teste criados com sucesso!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao criar alertas de teste: ' . $e->getMessage()]);
}
?>
