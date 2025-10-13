<?php
// criar_metas_exemplo.php - Criar metas de exemplo para teste
require_once 'templates/header.php';

try {
    // Verificar se a tabela existe e criar se necessário
    $stmt_check = $pdo->prepare("SHOW TABLES LIKE 'compras_futuras'");
    $stmt_check->execute();
    $table_exists = $stmt_check->fetch();
    
    if (!$table_exists) {
        // Criar tabela se não existir
        $create_table = "
            CREATE TABLE IF NOT EXISTS compras_futuras (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                nome_item VARCHAR(255) NOT NULL,
                valor_estimado DECIMAL(10,2) DEFAULT NULL,
                valor_total DECIMAL(10,2) DEFAULT NULL,
                valor_poupado DECIMAL(10,2) DEFAULT 0,
                link_referencia TEXT DEFAULT NULL,
                descricao TEXT DEFAULT NULL,
                prioridade ENUM('low', 'medium', 'high') DEFAULT 'medium',
                status ENUM('planejando', 'concluida') DEFAULT 'planejando',
                ordem INT DEFAULT 0,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                data_conclusao TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
            )
        ";
        $pdo->exec($create_table);
        echo "Tabela compras_futuras criada com sucesso!<br>";
    }
    
    // Verificar se já existem metas para este usuário
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM compras_futuras WHERE id_usuario = ?");
    $stmt_count->execute([$userId]);
    $count = $stmt_count->fetchColumn();
    
    if ($count > 0) {
        echo "Já existem $count metas para este usuário.<br>";
        echo "<a href='compras_futuras.php'>Voltar para Compras Futuras</a>";
        exit;
    }
    
    // Inserir metas de exemplo
    $metas_exemplo = [
        [
            'nome_item' => 'iPhone 15 Pro',
            'valor_estimado' => 8000.00,
            'link_referencia' => 'https://www.apple.com/br/iphone-15-pro/',
            'descricao' => 'Novo iPhone com câmera profissional',
            'prioridade' => 'high'
        ],
        [
            'nome_item' => 'Notebook Gamer',
            'valor_estimado' => 4500.00,
            'link_referencia' => 'https://www.amazon.com.br/',
            'descricao' => 'Notebook para jogos e trabalho',
            'prioridade' => 'medium'
        ],
        [
            'nome_item' => 'Viagem para Europa',
            'valor_estimado' => 15000.00,
            'link_referencia' => 'https://www.skyscanner.com.br/',
            'descricao' => 'Viagem de 15 dias pela Europa',
            'prioridade' => 'high'
        ],
        [
            'nome_item' => 'Curso de Programação',
            'valor_estimado' => 1200.00,
            'link_referencia' => 'https://www.udemy.com/',
            'descricao' => 'Curso completo de desenvolvimento web',
            'prioridade' => 'medium'
        ],
        [
            'nome_item' => 'Smartwatch',
            'valor_estimado' => 800.00,
            'link_referencia' => 'https://www.apple.com/br/watch/',
            'descricao' => 'Apple Watch para monitorar saúde',
            'prioridade' => 'low'
        ]
    ];
    
    $stmt_insert = $pdo->prepare("
        INSERT INTO compras_futuras (id_usuario, nome_item, valor_estimado, link_referencia, descricao, prioridade, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $inseridas = 0;
    foreach ($metas_exemplo as $meta) {
        $stmt_insert->execute([
            $userId,
            $meta['nome_item'],
            $meta['valor_estimado'],
            $meta['link_referencia'],
            $meta['descricao'],
            $meta['prioridade'],
            'planejando'
        ]);
        $inseridas++;
    }
    
    echo "✅ $inseridas metas de exemplo criadas com sucesso!<br>";
    echo "<a href='compras_futuras.php' class='btn btn-primary'>Ver Minhas Metas</a>";
    
} catch (PDOException $e) {
    echo "❌ Erro ao criar metas: " . $e->getMessage();
}
?>
