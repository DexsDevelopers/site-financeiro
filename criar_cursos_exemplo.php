<?php
// criar_cursos_exemplo.php - Criar cursos de exemplo
session_start();
require_once 'includes/db_connect.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die('Usuário não logado');
}

$userId = $_SESSION['user_id'];

try {
    // Verificar se a tabela existe
    $stmt_check = $pdo->prepare("SHOW TABLES LIKE 'cursos'");
    $stmt_check->execute();
    $table_exists = $stmt_check->fetch();
    
    if (!$table_exists) {
        // Criar tabela se não existir
        $create_table = "
            CREATE TABLE IF NOT EXISTS cursos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                nome_curso VARCHAR(255) NOT NULL,
                descricao TEXT DEFAULT NULL,
                plataforma VARCHAR(100) DEFAULT NULL,
                link_curso TEXT DEFAULT NULL,
                status ENUM('pendente', 'assistindo', 'concluido') DEFAULT 'pendente',
                progresso INT DEFAULT 0,
                data_inicio DATE DEFAULT NULL,
                data_conclusao DATE DEFAULT NULL,
                prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
                categoria VARCHAR(100) DEFAULT NULL,
                duracao_horas INT DEFAULT NULL,
                valor DECIMAL(10,2) DEFAULT NULL,
                ordem INT DEFAULT 0,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
            )
        ";
        $pdo->exec($create_table);
    }
    
    // Cursos de exemplo
    $cursos_exemplo = [
        [
            'nome_curso' => 'JavaScript Moderno ES6+',
            'descricao' => 'Aprenda as funcionalidades mais recentes do JavaScript incluindo ES6, ES7, ES8 e além.',
            'plataforma' => 'Udemy',
            'categoria' => 'programacao',
            'duracao_horas' => 40,
            'valor' => 199.90,
            'link_curso' => 'https://udemy.com/javascript-moderno',
            'prioridade' => 'alta',
            'status' => 'assistindo',
            'progresso' => 65
        ],
        [
            'nome_curso' => 'React.js Completo',
            'descricao' => 'Desenvolvimento de aplicações web modernas com React.js, Hooks e Context API.',
            'plataforma' => 'Coursera',
            'categoria' => 'programacao',
            'duracao_horas' => 60,
            'valor' => 299.90,
            'link_curso' => 'https://coursera.org/react-completo',
            'prioridade' => 'alta',
            'status' => 'pendente',
            'progresso' => 0
        ],
        [
            'nome_curso' => 'Design UX/UI Avançado',
            'descricao' => 'Princípios avançados de design de experiência do usuário e interface.',
            'plataforma' => 'Alura',
            'categoria' => 'design',
            'duracao_horas' => 30,
            'valor' => 149.90,
            'link_curso' => 'https://alura.com.br/ux-ui-avancado',
            'prioridade' => 'media',
            'status' => 'concluido',
            'progresso' => 100
        ],
        [
            'nome_curso' => 'Marketing Digital Completo',
            'descricao' => 'Estratégias de marketing digital, SEO, SEM e redes sociais.',
            'plataforma' => 'Hotmart',
            'categoria' => 'marketing',
            'duracao_horas' => 50,
            'valor' => 399.90,
            'link_curso' => 'https://hotmart.com/marketing-digital',
            'prioridade' => 'media',
            'status' => 'pendente',
            'progresso' => 0
        ],
        [
            'nome_curso' => 'Inglês para Programadores',
            'descricao' => 'Aprenda inglês técnico focado em programação e tecnologia.',
            'plataforma' => 'Duolingo',
            'categoria' => 'idiomas',
            'duracao_horas' => 80,
            'valor' => 0,
            'link_curso' => 'https://duolingo.com/english-programmers',
            'prioridade' => 'baixa',
            'status' => 'assistindo',
            'progresso' => 25
        ]
    ];
    
    // Inserir cursos
    $stmt = $pdo->prepare("
        INSERT INTO cursos (
            id_usuario, nome_curso, descricao, plataforma, categoria, 
            duracao_horas, valor, link_curso, prioridade, status, 
            progresso, data_criacao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $cursos_criados = 0;
    foreach ($cursos_exemplo as $curso) {
        $result = $stmt->execute([
            $userId, 
            $curso['nome_curso'], 
            $curso['descricao'], 
            $curso['plataforma'], 
            $curso['categoria'],
            $curso['duracao_horas'], 
            $curso['valor'], 
            $curso['link_curso'], 
            $curso['prioridade'],
            $curso['status'],
            $curso['progresso']
        ]);
        
        if ($result) {
            $cursos_criados++;
        }
    }
    
    echo "✅ $cursos_criados cursos de exemplo criados com sucesso!<br>";
    echo "<a href='cursos.php'>Ver meus cursos</a>";
    
} catch (PDOException $e) {
    echo "❌ Erro ao criar cursos: " . $e->getMessage();
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage();
}
?>
