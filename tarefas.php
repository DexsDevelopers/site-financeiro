    <?php
    require_once 'templates/header.php';
    require_once 'includes/db_connect.php';

    // ===== CONFIGURAÇÕES E INICIALIZAÇÃO =====
    $dataHoje = date('Y-m-d');
    $dataAmanha = date('Y-m-d', strtotime('+1 day'));

    // ===== CRIAR TABELAS NECESSÁRIAS =====
    try {
        // Tabela rotinas_fixas (hábitos permanentes)
        $sql_rotinas_fixas = "
        CREATE TABLE IF NOT EXISTS rotinas_fixas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT DEFAULT NULL,
            horario_sugerido TIME DEFAULT NULL,
            dias_semana SET('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo') DEFAULT NULL,
            cor VARCHAR(7) DEFAULT '#007bff',
            icone VARCHAR(50) DEFAULT 'bi-check-circle',
            ordem INT DEFAULT 0,
            ativo BOOLEAN DEFAULT TRUE,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            UNIQUE KEY unique_rotina_usuario (id_usuario, nome)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql_rotinas_fixas);
        
        // Tabela rotina_controle_diario (controle de execução)
        $sql_controle = "
        CREATE TABLE IF NOT EXISTS rotina_controle_diario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            id_rotina_fixa INT NOT NULL,
            data_execucao DATE NOT NULL,
            status ENUM('pendente', 'concluido', 'pulado') DEFAULT 'pendente',
            horario_execucao TIME DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (id_rotina_fixa) REFERENCES rotinas_fixas(id) ON DELETE CASCADE,
            UNIQUE KEY unique_controle_dia (id_usuario, id_rotina_fixa, data_execucao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql_controle);
        
        // Tabela rotinas_diarias (tarefas específicas do dia)
        $sql_rotinas_diarias = "
        CREATE TABLE IF NOT EXISTS rotinas_diarias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT DEFAULT NULL,
            horario TIME DEFAULT NULL,
            status ENUM('pendente', 'concluido', 'pulado') DEFAULT 'pendente',
            prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
            cor VARCHAR(7) DEFAULT '#28a745',
            icone VARCHAR(50) DEFAULT 'bi-calendar-day',
            ordem INT DEFAULT 0,
            data_execucao DATE NOT NULL,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql_rotinas_diarias);
        
    } catch (PDOException $e) {
        error_log("Erro ao criar tabelas: " . $e->getMessage());
    }

    // ===== BUSCAR DADOS =====
    $rotinasFixas = [];
    $rotinasDiarias = [];
    $tarefasPendentes = [];
    $tarefasConcluidas = [];
    $estatisticas = [];

    try {
        // Buscar rotinas fixas
        $stmt = $pdo->prepare("
            SELECT rf.*, 
                rcd.status as status_hoje,
                rcd.horario_execucao,
                rcd.observacoes
            FROM rotinas_fixas rf
            LEFT JOIN rotina_controle_diario rcd ON rf.id = rcd.id_rotina_fixa 
                AND rcd.id_usuario = rf.id_usuario 
                AND rcd.data_execucao = ?
            WHERE rf.id_usuario = ? AND rf.ativo = TRUE
            ORDER BY rf.ordem, rf.horario_sugerido
        ");
        $stmt->execute([$dataHoje, $userId]);
        $rotinasFixas = $stmt->fetchAll();
        
        // Criar controles para hoje se não existirem
        foreach ($rotinasFixas as $rotina) {
            if ($rotina["status_hoje"] === null) {
                $stmt = $pdo->prepare("
                    INSERT INTO rotina_controle_diario (id_usuario, id_rotina_fixa, data_execucao, status) 
                    VALUES (?, ?, ?, 'pendente')
                ");
                $stmt->execute([$userId, $rotina["id"], $dataHoje]);
            }
        }
        
        // Buscar rotinas diárias
        $stmt = $pdo->prepare("
            SELECT * FROM rotinas_diarias 
            WHERE id_usuario = ? AND data_execucao = ? 
            ORDER BY ordem, horario
        ");
        $stmt->execute([$userId, $dataHoje]);
        $rotinasDiarias = $stmt->fetchAll();
        
        // Buscar tarefas pendentes
        $stmt = $pdo->prepare("
            SELECT * FROM tarefas 
            WHERE id_usuario = ? AND status = 'pendente' 
            ORDER BY FIELD(prioridade, 'Alta', 'Média', 'Baixa'), ordem ASC
        ");
        $stmt->execute([$userId]);
        $tarefasPendentes = $stmt->fetchAll();
        
        // Buscar tarefas concluídas (últimas 10)
        $stmt = $pdo->prepare("
            SELECT * FROM tarefas 
            WHERE id_usuario = ? AND status = 'concluida' 
            ORDER BY data_criacao DESC LIMIT 10
        ");
        $stmt->execute([$userId]);
        $tarefasConcluidas = $stmt->fetchAll();
        
        // Calcular estatísticas
        $totalRotinasFixas = count($rotinasFixas);
        $rotinasFixasConcluidas = count(array_filter($rotinasFixas, function($r) { 
            return $r["status_hoje"] === "concluido"; 
        }));
        $progressoRotinasFixas = $totalRotinasFixas > 0 ? ($rotinasFixasConcluidas / $totalRotinasFixas) * 100 : 0;
        
        $totalRotinasDiarias = count($rotinasDiarias);
        $rotinasDiariasConcluidas = count(array_filter($rotinasDiarias, function($r) { 
            return $r["status"] === "concluido"; 
        }));
        $progressoRotinasDiarias = $totalRotinasDiarias > 0 ? ($rotinasDiariasConcluidas / $totalRotinasDiarias) * 100 : 0;
        
        $totalTarefas = count($tarefasPendentes);
        $tarefasConcluidasHoje = count($tarefasConcluidas);
        
        $estatisticas = [
            'rotinas_fixas' => [
                'total' => $totalRotinasFixas,
                'concluidas' => $rotinasFixasConcluidas,
                'progresso' => $progressoRotinasFixas
            ],
            'rotinas_diarias' => [
                'total' => $totalRotinasDiarias,
                'concluidas' => $rotinasDiariasConcluidas,
                'progresso' => $progressoRotinasDiarias
            ],
            'tarefas' => [
                'pendentes' => $totalTarefas,
                'concluidas_hoje' => $tarefasConcluidasHoje
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar dados: " . $e->getMessage());
    }

    // ===== FUNÇÕES AUXILIARES =====
    function getPrioridadeBadge($prioridade) {
        switch ($prioridade) {
            case 'Alta': return 'bg-danger';
            case 'Média': return 'bg-warning text-dark';
            case 'Baixa': return 'bg-success';
            default: return 'bg-secondary';
        }
    }

    function getStatusBadge($status) {
        switch ($status) {
            case 'concluido': return 'bg-success';
            case 'pendente': return 'bg-warning text-dark';
            case 'pulado': return 'bg-secondary';
            default: return 'bg-light text-dark';
        }
    }

    function getStatusText($status) {
        switch ($status) {
            case 'concluido': return 'Concluído';
            case 'pendente': return 'Pendente';
            case 'pulado': return 'Pulado';
            default: return 'Desconhecido';
        }
    }

    function formatarTempo($minutos) {
        if ($minutos <= 0) return '0min';
        $h = floor($minutos / 60);
        $m = $minutos % 60;
        $resultado = '';
        if ($h > 0) $resultado .= $h . 'h ';
        if ($m > 0) $resultado .= $m . 'min';
        return trim($resultado);
    }

    function getDiasSemana($dias) {
        if (!$dias) return [];
        return explode(',', $dias);
    }

    function isDiaAtual($dias) {
        if (!$dias) return true;
        $diasArray = getDiasSemana($dias);
        $diaAtual = strtolower(date('l'));
        $diasMap = [
            'monday' => 'segunda',
            'tuesday' => 'terca', 
            'wednesday' => 'quarta',
            'thursday' => 'quinta',
            'friday' => 'sexta',
            'saturday' => 'sabado',
            'sunday' => 'domingo'
        ];
        return in_array($diasMap[$diaAtual], $diasArray);
    }
    ?>

    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard de Tarefas - Sistema Moderno</title>
        
        <!-- Bootstrap 5.3 -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <!-- Sortable.js -->
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        
        <style>
        :root {
            /* Vermelho sofisticado com gradientes */
            --primary-red: #DC143C;
            --red-dark: #8B0000;
            --red-light: #FF6B6B;
            --red-glow: rgba(220, 20, 60, 0.4);
            
            /* Preto e cinzas modernos */
            --dark-bg: #0A0A0A;
            --card-bg: #151515;
            --card-hover: #1F1F1F;
            --border-color: #2A2A2A;
            --border-hover: #DC143C;
            
            /* Textos com hierarquia */
            --text-primary: #FFFFFF;
            --text-secondary: #B0B0B0;
            --text-muted: #707070;
            
            /* Cores de status modernas */
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #DC143C;
            --info: #3B82F6;
            
            /* Efeitos e animações */
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 8px 32px rgba(220, 20, 60, 0.5);
            --shadow-glow: 0 0 40px rgba(220, 20, 60, 0.3);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(135deg, #0A0A0A 0%, #1A0A0F 100%);
            color: var(--text-primary);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Roboto, sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(220, 20, 60, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .main-container {
            background: rgba(21, 21, 21, 0.9);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow), 0 0 0 1px rgba(220, 20, 60, 0.1);
            margin: 2rem auto;
            max-width: 1400px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            position: relative;
            z-index: 1;
            transition: var(--transition);
        }

        .main-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary-red), transparent);
            opacity: 0.5;
        }

        .header-section {
            background: linear-gradient(135deg, var(--red-dark) 0%, var(--primary-red) 50%, var(--red-light) 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .header-section::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: headerPulse 15s ease-in-out infinite;
        }

        @keyframes headerPulse {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-20px, 20px) scale(1.1); }
        }

            .header-section::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
                opacity: 0.1;
            }

            .header-content {
                position: relative;
                z-index: 1;
            }

            .page-title {
                font-size: 2.5rem;
                font-weight: 800;
                margin-bottom: 0.5rem;
                background: linear-gradient(45deg, #fff, #e0e7ff);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .page-subtitle {
                font-size: 1.1rem;
                opacity: 0.9;
                margin-bottom: 0;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
                margin-top: 2rem;
            }

            .stat-card {
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: var(--radius-lg);
                padding: 1.5rem;
                text-align: center;
                transition: all 0.3s ease;
            }

            .stat-card:hover {
                transform: translateY(-2px);
                background: rgba(255, 255, 255, 0.2);
            }

            .stat-icon {
                font-size: 2.5rem;
                margin-bottom: 1rem;
                opacity: 0.9;
            }

            .stat-value {
                font-size: 2rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
            }

            .stat-label {
                font-size: 0.9rem;
                opacity: 0.8;
            }

            .content-section {
                padding: 2rem;
            }

        .section-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            transition: var(--transition);
            position: relative;
        }

        .section-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(220, 20, 60, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .section-card:hover::before {
            left: 100%;
        }

        .section-card:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow-hover);
            transform: translateY(-4px);
        }

            .section-header {
                background: var(--primary-red);
                padding: 1.5rem;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                align-items: center;
                justify-content: space-between;
                color: var(--text-primary);
            }

            .section-title {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin: 0;
                color: var(--text-primary);
            }

            .section-title h3 {
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--text-primary);
                margin: 0;
            }

            .section-icon {
                width: 40px;
                height: 40px;
                border-radius: var(--radius-md);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
            }

            .progress-ring {
                width: 60px;
                height: 60px;
                position: relative;
            }

            .progress-ring svg {
                width: 100%;
                height: 100%;
                transform: rotate(-90deg);
            }

            .progress-ring circle {
                fill: none;
                stroke-width: 4;
            }

            .progress-ring .background {
                stroke: var(--border-color);
            }

            .progress-ring .progress {
                stroke: var(--primary-color);
                stroke-linecap: round;
                transition: stroke-dasharray 0.3s ease;
            }

            .progress-text {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-weight: 700;
                font-size: 0.875rem;
                color: var(--text-primary);
            }

            .rotina-item {
                background: white;
                border: 1px solid var(--border-color);
                border-radius: var(--radius-md);
                padding: 1rem;
                margin-bottom: 0.75rem;
                transition: all 0.3s ease;
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }

            .rotina-item::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: var(--primary-color);
                transition: all 0.3s ease;
            }

            .rotina-item:hover {
                transform: translateY(-1px);
                box-shadow: var(--shadow-md);
                border-color: var(--primary-color);
            }

            .rotina-item.completed {
                background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
                border-color: var(--success-color);
            }

            .rotina-item.completed::before {
                background: var(--success-color);
            }

            .rotina-item.pulada {
                background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                border-color: var(--danger-color);
            }

            .rotina-item.pulada::before {
                background: var(--danger-color);
            }

            .rotina-header {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 0.5rem;
            }

            .rotina-icon {
                width: 40px;
                height: 40px;
                border-radius: var(--radius-md);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                color: white;
                flex-shrink: 0;
            }

            .rotina-content {
                flex: 1;
            }

            .rotina-name {
                font-weight: 600;
                color: var(--text-primary);
                margin-bottom: 0.25rem;
            }

            .rotina-description {
                color: var(--text-secondary);
                font-size: 0.875rem;
                margin-bottom: 0.5rem;
            }

            .rotina-meta {
                display: flex;
                align-items: center;
                gap: 1rem;
                font-size: 0.875rem;
                color: var(--text-secondary);
            }

            .rotina-time {
                display: flex;
                align-items: center;
                gap: 0.25rem;
            }

            .rotina-status {
                padding: 0.25rem 0.75rem;
                border-radius: var(--radius-sm);
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .rotina-actions {
                display: flex;
                gap: 0.5rem;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .rotina-item:hover .rotina-actions {
                opacity: 1;
            }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-size: 0.875rem;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-action::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: var(--red-glow);
            transform: translate(-50%, -50%);
            transition: width 0.4s ease, height 0.4s ease;
        }

        .btn-action:hover::before {
            width: 100%;
            height: 100%;
        }

        .btn-action:hover {
            background: var(--primary-red);
            color: var(--text-primary);
            border-color: var(--primary-red);
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 4px 12px var(--red-glow);
        }

        .btn-action i {
            position: relative;
            z-index: 1;
        }

            .btn-action.danger:hover {
                background: var(--danger);
                border-color: var(--danger);
            }

            .btn-action.success:hover {
                background: var(--success);
                border-color: var(--success);
            }

        .task-card {
            background: linear-gradient(135deg, var(--card-bg) 0%, rgba(31, 31, 31, 0.8) 100%);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
            position: relative;
            color: var(--text-primary);
            overflow: hidden;
        }

        .task-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, var(--red-glow) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .task-card:hover::after {
            opacity: 1;
        }

            .task-card::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: var(--border-color);
                border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            }

            .task-card.prioridade-Alta::before { background: var(--danger); }
            .task-card.prioridade-Média::before { background: var(--warning); }
            .task-card.prioridade-Baixa::before { background: var(--success); }

        .task-card:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: var(--shadow-hover);
            border-color: var(--border-hover);
        }

            .task-header {
                display: flex;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .task-content {
                flex: 1;
            }

            .task-title {
                font-size: 1.1rem;
                font-weight: 600;
                color: var(--text-primary);
                margin-bottom: 0.5rem;
            }

            .task-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 1rem;
                align-items: center;
                color: var(--text-secondary);
                font-size: 0.875rem;
            }

            .task-actions {
                display: flex;
                gap: 0.5rem;
            }

            .btn-task {
                width: 36px;
                height: 36px;
                border-radius: var(--radius-sm);
                border: 1px solid var(--border-color);
                background: white;
                color: var(--text-secondary);
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s ease;
                font-size: 0.875rem;
            }

            .btn-task:hover {
                background: var(--primary-color);
                color: white;
                border-color: var(--primary-color);
                transform: scale(1.05);
            }

            .empty-state {
                text-align: center;
                padding: 3rem 2rem;
                color: var(--text-secondary);
            }

            .empty-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
                opacity: 0.5;
            }

            .floating-actions {
                position: fixed;
                bottom: 2rem;
                right: 2rem;
                display: flex;
                flex-direction: column;
                gap: 1rem;
                z-index: 1000;
            }

            .btn-floating {
                width: 56px;
                height: 56px;
                border-radius: 50%;
                border: none;
                background: var(--primary-color);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                box-shadow: var(--shadow-lg);
                transition: all 0.3s ease;
            }

            .btn-floating:hover {
                transform: scale(1.1);
                box-shadow: var(--shadow-xl);
            }

            .btn-floating.secondary {
                background: var(--secondary-color);
            }

            .btn-floating.success {
                background: var(--success-color);
            }

            .modal-content {
                border: none;
                border-radius: var(--radius);
                box-shadow: var(--shadow-hover);
                background: var(--card-bg);
                color: var(--text-primary);
            }

            .modal-header {
                background: var(--primary-red);
                border-bottom: 1px solid var(--border-color);
                border-radius: var(--radius) var(--radius) 0 0;
                color: var(--text-primary);
            }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            transition: var(--transition);
            background: rgba(21, 21, 21, 0.6);
            color: var(--text-primary);
            backdrop-filter: blur(10px);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px var(--red-glow), 0 0 20px var(--red-glow);
            background: rgba(31, 31, 31, 0.8);
            color: var(--text-primary);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        /* Efeito de brilho nos inputs */
        .form-control:hover, .form-select:hover {
            border-color: var(--text-secondary);
            background: rgba(25, 25, 25, 0.7);
        }

            .btn {
                border-radius: var(--radius-md);
                font-weight: 600;
                padding: 0.75rem 1.5rem;
                transition: all 0.3s ease;
            }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--red-dark) 100%);
            border: none;
            color: var(--text-primary);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px var(--red-glow);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--red-light) 0%, var(--primary-red) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
            color: var(--text-primary);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

            .btn-success {
                background: var(--success);
                border-color: var(--success);
                color: var(--dark-bg);
            }

            .btn-warning {
                background: var(--warning);
                border-color: var(--warning);
                color: var(--dark-bg);
            }

            .btn-danger {
                background: var(--danger);
                border-color: var(--danger);
                color: var(--text-primary);
            }

            .badge {
                font-size: 0.75rem;
                font-weight: 600;
                padding: 0.375rem 0.75rem;
                border-radius: var(--radius);
                color: var(--text-primary);
            }

            .toast-container {
                position: fixed;
                top: 2rem;
                right: 2rem;
                z-index: 1050;
            }

            .toast {
                border: none;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-lg);
            }

            .toast-header {
                background: var(--card-bg);
                border-bottom: 1px solid var(--border-color);
                color: var(--text-primary);
            }

            .header-actions {
                margin-top: 1rem;
            }

            .stat-card-large {
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                border-radius: var(--radius);
                padding: 1.5rem;
                margin-bottom: 1rem;
            }

            .stat-card-large h6 {
                color: var(--text-primary);
                margin-bottom: 1rem;
            }

            .progress {
                height: 8px;
                background-color: rgba(255, 255, 255, 0.1);
            }

            .progress-bar {
                background-color: var(--primary-red);
            }

        /* Animações de entrada */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .section-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .task-card, .rotina-item {
            animation: slideInRight 0.4s ease-out;
        }

        .btn-action {
            animation: scaleIn 0.3s ease-out;
        }

        /* Efeito de pulso para elementos importantes */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 var(--red-glow);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 20, 60, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 20, 60, 0);
            }
        }

        .stat-card:hover {
            animation: pulse 2s infinite;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                    border-radius: var(--radius-lg);
                }
                
                .header-section {
                    padding: 1.5rem;
                }
                
                .page-title {
                    font-size: 2rem;
                }
                
                .content-section {
                    padding: 1.5rem;
                }
                
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                }
                
                .floating-actions {
                    bottom: 1rem;
                    right: 1rem;
                }
                
                .btn-floating {
                    width: 48px;
                    height: 48px;
                    font-size: 1rem;
                }
            }

            @media (max-width: 576px) {
                .main-container {
                    margin: 0.5rem;
                }
                
                .header-section {
                    padding: 1rem;
                }
                
                .content-section {
                    padding: 1rem;
                }
                
                .stats-grid {
                    grid-template-columns: 1fr;
                }
                
                .rotina-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.5rem;
                }
                
                .task-header {
                    flex-direction: column;
                    gap: 0.75rem;
                }
                
                .task-actions {
                    width: 100%;
                    justify-content: space-between;
                }
            }

            /* Animações */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .fade-in-up {
                animation: fadeInUp 0.6s ease forwards;
            }

            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.05);
                }
            }

            .pulse {
                animation: pulse 2s infinite;
            }

            /* Scrollbar personalizada */
            ::-webkit-scrollbar {
                width: 8px;
            }

            ::-webkit-scrollbar-track {
                background: var(--light-color);
            }

            ::-webkit-scrollbar-thumb {
                background: var(--border-color);
                border-radius: var(--radius-sm);
            }

            ::-webkit-scrollbar-thumb:hover {
                background: var(--text-secondary);
            }
        </style>
    </head>

    <body>
        <div class="main-container">
            <!-- Header Section -->
            <div class="header-section">
                <div class="header-content">
                    <h1 class="page-title">
                        <i class="bi bi-calendar-check me-3"></i>
                        Dashboard de Tarefas
                    </h1>
                    <p class="page-subtitle">Organize sua rotina e maximize sua produtividade</p>
                    
                    <!-- Botão de Estatísticas -->
                    <div class="header-actions">
                        <button class="btn btn-outline-info" onclick="mostrarEstatisticas()">
                            <i class="bi bi-graph-up me-2"></i>Ver Estatísticas
                        </button>
                    </div>
                    
                    <!-- Estatísticas -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="bi bi-arrow-repeat"></i>
                            </div>
                            <div class="stat-value"><?php echo $estatisticas['rotinas_fixas']['concluidas']; ?>/<?php echo $estatisticas['rotinas_fixas']['total']; ?></div>
                            <div class="stat-label">Rotinas Fixas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="bi bi-calendar-day"></i>
                            </div>
                            <div class="stat-value"><?php echo $estatisticas['rotinas_diarias']['concluidas']; ?>/<?php echo $estatisticas['rotinas_diarias']['total']; ?></div>
                            <div class="stat-label">Rotinas Diárias</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="bi bi-list-task"></i>
                            </div>
                            <div class="stat-value"><?php echo $estatisticas['tarefas']['pendentes']; ?></div>
                            <div class="stat-label">Tarefas Pendentes</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo $estatisticas['tarefas']['concluidas_hoje']; ?></div>
                            <div class="stat-label">Concluídas Hoje</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Section -->
            <div class="content-section">
                <div class="row">
                    <!-- Rotinas Fixas -->
                    <div class="col-lg-6 mb-4">
                        <div class="section-card">
                            <div class="section-header">
                                <div class="section-title">
                                    <div class="section-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white;">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <div>
                                        <h3>Rotinas Fixas</h3>
                                        <small class="text-muted">Hábitos permanentes</small>
                                    </div>
                                </div>
                                <div class="progress-ring">
                                    <svg>
                                        <circle class="background" cx="30" cy="30" r="26"></circle>
                                        <circle class="progress" cx="30" cy="30" r="26" 
                                                stroke-dasharray="<?php echo 2 * 3.14159 * 26; ?>" 
                                                stroke-dashoffset="<?php echo 2 * 3.14159 * 26 * (1 - $estatisticas['rotinas_fixas']['progresso'] / 100); ?>">
                                        </circle>
                                    </svg>
                                    <div class="progress-text"><?php echo round($estatisticas['rotinas_fixas']['progresso']); ?>%</div>
                                </div>
                            </div>
                            <div class="p-3">
                                <?php if (empty($rotinasFixas)): ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="bi bi-calendar-check"></i>
                                        </div>
                                        <h5>Nenhuma rotina fixa</h5>
                                        <p class="text-muted">Adicione hábitos que você quer fazer regularmente</p>
                                        <button class="btn btn-primary" onclick="abrirModalRotinaFixa()">
                                            <i class="bi bi-plus-circle me-2"></i>Adicionar Rotina Fixa
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div id="lista-rotinas-fixas">
                                        <?php foreach ($rotinasFixas as $rotina): ?>
                                            <?php if (isDiaAtual($rotina['dias_semana'])): ?>
                                                <div class="rotina-item <?php echo $rotina['status_hoje'] === 'concluido' ? 'completed' : ($rotina['status_hoje'] === 'pulado' ? 'pulada' : ''); ?>" 
                                                    data-rotina-id="<?php echo $rotina['id']; ?>">
                                                    <div class="rotina-header">
                                                        <div class="rotina-icon" style="background: <?php echo $rotina['cor']; ?>;">
                                                            <i class="<?php echo $rotina['icone']; ?>"></i>
                                                        </div>
                                                        <div class="rotina-content">
                                                            <div class="rotina-name"><?php echo htmlspecialchars($rotina['nome']); ?></div>
                                                            <?php if ($rotina['descricao']): ?>
                                                                <div class="rotina-description"><?php echo htmlspecialchars($rotina['descricao']); ?></div>
                                                            <?php endif; ?>
                                                            <div class="rotina-meta">
                                                                <?php if ($rotina['horario_sugerido']): ?>
                                                                    <div class="rotina-time">
                                                                        <i class="bi bi-clock"></i>
                                                                        <?php echo date('H:i', strtotime($rotina['horario_sugerido'])); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="rotina-status badge <?php echo getStatusBadge($rotina['status_hoje']); ?>">
                                                                    <?php echo getStatusText($rotina['status_hoje']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="rotina-actions">
                                                            <button class="btn-action success" onclick="toggleRotinaFixa(<?php echo $rotina['id']; ?>, '<?php echo $rotina['status_hoje']; ?>')" 
                                                                    title="<?php echo $rotina['status_hoje'] === 'concluido' ? 'Marcar como pendente' : 'Marcar como concluído'; ?>">
                                                                <i class="bi bi-<?php echo $rotina['status_hoje'] === 'concluido' ? 'arrow-counterclockwise' : 'check'; ?>"></i>
                                                            </button>
                                                            <button class="btn-action" onclick="editarRotinaFixa(<?php echo $rotina['id']; ?>)" title="Editar">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn-action danger" onclick="excluirRotinaFixa(<?php echo $rotina['id']; ?>, '<?php echo htmlspecialchars($rotina['nome'], ENT_QUOTES); ?>')" title="Excluir">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Rotinas Diárias -->
                    <div class="col-lg-6 mb-4">
                        <div class="section-card">
                            <div class="section-header">
                                <div class="section-title">
                                    <div class="section-icon" style="background: linear-gradient(135deg, var(--success-color), var(--info-color)); color: white;">
                                        <i class="bi bi-calendar-day"></i>
                                    </div>
                                    <div>
                                        <h3>Rotinas Diárias</h3>
                                        <small class="text-muted">Tarefas específicas de hoje</small>
                                    </div>
                                </div>
                                <div class="progress-ring">
                                    <svg>
                                        <circle class="background" cx="30" cy="30" r="26"></circle>
                                        <circle class="progress" cx="30" cy="30" r="26" 
                                                stroke-dasharray="<?php echo 2 * 3.14159 * 26; ?>" 
                                                stroke-dashoffset="<?php echo 2 * 3.14159 * 26 * (1 - $estatisticas['rotinas_diarias']['progresso'] / 100); ?>">
                                        </circle>
                                    </svg>
                                    <div class="progress-text"><?php echo round($estatisticas['rotinas_diarias']['progresso']); ?>%</div>
                                </div>
                            </div>
                            <div class="p-3">
                                <?php if (empty($rotinasDiarias)): ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="bi bi-calendar-day"></i>
                                        </div>
                                        <h5>Nenhuma rotina diária</h5>
                                        <p class="text-muted">Adicione tarefas específicas para hoje</p>
                                        <button class="btn btn-success" onclick="abrirModalRotinaDiaria()">
                                            <i class="bi bi-plus-circle me-2"></i>Adicionar Rotina Diária
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div id="lista-rotinas-diarias">
                                        <?php foreach ($rotinasDiarias as $rotina): ?>
                                            <div class="rotina-item <?php echo $rotina['status'] === 'concluido' ? 'completed' : ($rotina['status'] === 'pulado' ? 'pulada' : ''); ?>" 
                                                data-rotina-id="<?php echo $rotina['id']; ?>">
                                                <div class="rotina-header">
                                                    <div class="rotina-icon" style="background: <?php echo $rotina['cor']; ?>;">
                                                        <i class="<?php echo $rotina['icone']; ?>"></i>
                                                    </div>
                                                    <div class="rotina-content">
                                                        <div class="rotina-name"><?php echo htmlspecialchars($rotina['nome']); ?></div>
                                                        <?php if ($rotina['descricao']): ?>
                                                            <div class="rotina-description"><?php echo htmlspecialchars($rotina['descricao']); ?></div>
                                                        <?php endif; ?>
                                                        <div class="rotina-meta">
                                                            <?php if ($rotina['horario']): ?>
                                                                <div class="rotina-time">
                                                                    <i class="bi bi-clock"></i>
                                                                    <?php echo date('H:i', strtotime($rotina['horario'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="rotina-status badge <?php echo getStatusBadge($rotina['status']); ?>">
                                                                <?php echo getStatusText($rotina['status']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="rotina-actions">
                                                        <button class="btn-action success" onclick="toggleRotinaDiaria(<?php echo $rotina['id']; ?>, '<?php echo $rotina['status']; ?>')" 
                                                                title="<?php echo $rotina['status'] === 'concluido' ? 'Marcar como pendente' : 'Marcar como concluído'; ?>">
                                                            <i class="bi bi-<?php echo $rotina['status'] === 'concluido' ? 'arrow-counterclockwise' : 'check'; ?>"></i>
                                                        </button>
                                                        <button class="btn-action" onclick="editarRotinaDiaria(<?php echo $rotina['id']; ?>)" title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn-action danger" onclick="excluirRotinaDiaria(<?php echo $rotina['id']; ?>, '<?php echo htmlspecialchars($rotina['nome'], ENT_QUOTES); ?>')" title="Excluir">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tarefas Pendentes -->
                    <div class="col-12">
                        <div class="section-card">
                            <div class="section-header">
                                <div class="section-title">
                                    <div class="section-icon" style="background: linear-gradient(135deg, var(--warning-color), var(--danger-color)); color: white;">
                                        <i class="bi bi-list-task"></i>
                                    </div>
                                    <div>
                                        <h3>Minhas Tarefas</h3>
                                        <small class="text-muted">Tarefas pendentes e concluídas</small>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3">
                                <?php if (empty($tarefasPendentes)): ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                        <h5>Nenhuma tarefa pendente!</h5>
                                        <p class="text-muted">Parabéns! Você está em dia com suas tarefas.</p>
                                        <button class="btn btn-primary" onclick="abrirModalTarefa()">
                                            <i class="bi bi-plus-circle me-2"></i>Nova Tarefa
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div id="lista-tarefas">
                                        <?php foreach ($tarefasPendentes as $tarefa): ?>
                                            <div class="task-card prioridade-<?php echo $tarefa['prioridade']; ?>" data-tarefa-id="<?php echo $tarefa['id']; ?>">
                                                <div class="task-header">
                                                    <div class="task-content">
                                                        <div class="task-title"><?php echo htmlspecialchars($tarefa['descricao']); ?></div>
                                                        <div class="task-meta">
                                                            <span class="badge <?php echo getPrioridadeBadge($tarefa['prioridade']); ?>">
                                                                <?php echo $tarefa['prioridade']; ?>
                                                            </span>
                                                            <?php if ($tarefa['data_limite']): ?>
                                                                <span><i class="bi bi-calendar-event me-1"></i><?php echo date('d/m/Y', strtotime($tarefa['data_limite'])); ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($tarefa['tempo_estimado'] > 0): ?>
                                                                <span><i class="bi bi-clock me-1"></i><?php echo formatarTempo($tarefa['tempo_estimado']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="task-actions">
                                                        <button class="btn-task" onclick="concluirTarefa(<?php echo $tarefa['id']; ?>)" title="Concluir">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                        <button class="btn-task" onclick="editarTarefa(<?php echo $tarefa['id']; ?>)" title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn-task danger" onclick="excluirTarefa(<?php echo $tarefa['id']; ?>, '<?php echo htmlspecialchars($tarefa['descricao'], ENT_QUOTES); ?>')" title="Excluir">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Action Buttons -->
        <div class="floating-actions">
            <button class="btn-floating success" onclick="abrirModalRotinaDiaria()" title="Nova Rotina Diária">
                <i class="bi bi-calendar-day"></i>
            </button>
            <button class="btn-floating secondary" onclick="abrirModalRotinaFixa()" title="Nova Rotina Fixa">
                <i class="bi bi-arrow-repeat"></i>
            </button>
            <button class="btn-floating" onclick="abrirModalTarefa()" title="Nova Tarefa">
                <i class="bi bi-plus"></i>
            </button>
        </div>

        <!-- Toast Container -->
        <div class="toast-container"></div>

        <!-- Modais serão adicionados aqui -->
        <!-- Modal Rotina Fixa -->
        <div class="modal fade" id="modalRotinaFixa" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-arrow-repeat me-2"></i>Nova Rotina Fixa
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formRotinaFixa">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome da Rotina</label>
                                    <input type="text" class="form-control" name="nome" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Horário Sugerido</label>
                                    <input type="time" class="form-control" name="horario_sugerido">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" name="descricao" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dias da Semana</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="segunda" id="segunda">
                                        <label class="form-check-label" for="segunda">Segunda</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="terca" id="terca">
                                        <label class="form-check-label" for="terca">Terça</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="quarta" id="quarta">
                                        <label class="form-check-label" for="quarta">Quarta</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="quinta" id="quinta">
                                        <label class="form-check-label" for="quinta">Quinta</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="sexta" id="sexta">
                                        <label class="form-check-label" for="sexta">Sexta</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="sabado" id="sabado">
                                        <label class="form-check-label" for="sabado">Sábado</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]" value="domingo" id="domingo">
                                        <label class="form-check-label" for="domingo">Domingo</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Cor</label>
                                    <input type="color" class="form-control form-control-color" name="cor" value="#007bff">
                                    <label class="form-label mt-3">Ícone</label>
                                    <select class="form-select" name="icone">
                                        <option value="bi-check-circle">Check Circle</option>
                                        <option value="bi-heart">Heart</option>
                                        <option value="bi-star">Star</option>
                                        <option value="bi-book">Book</option>
                                        <option value="bi-dumbbell">Dumbbell</option>
                                        <option value="bi-cup">Cup</option>
                                        <option value="bi-bicycle">Bicycle</option>
                                        <option value="bi-music-note">Music</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Salvar Rotina Fixa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Rotina Diária -->
        <div class="modal fade" id="modalRotinaDiaria" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-calendar-day me-2"></i>Nova Rotina Diária
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formRotinaDiaria">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome da Rotina</label>
                                    <input type="text" class="form-control" name="nome" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Horário</label>
                                    <input type="time" class="form-control" name="horario">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea class="form-control" name="descricao" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Prioridade</label>
                                    <select class="form-select" name="prioridade">
                                        <option value="baixa">Baixa</option>
                                        <option value="media" selected>Média</option>
                                        <option value="alta">Alta</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cor</label>
                                    <input type="color" class="form-control form-control-color" name="cor" value="#28a745">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ícone</label>
                                    <select class="form-select" name="icone">
                                        <option value="bi-calendar-day">Calendar Day</option>
                                        <option value="bi-check-circle">Check Circle</option>
                                        <option value="bi-heart">Heart</option>
                                        <option value="bi-star">Star</option>
                                        <option value="bi-book">Book</option>
                                        <option value="bi-dumbbell">Dumbbell</option>
                                        <option value="bi-cup">Cup</option>
                                        <option value="bi-bicycle">Bicycle</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg me-2"></i>Salvar Rotina Diária
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Tarefa -->
        <div class="modal fade" id="modalTarefa" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-list-task me-2"></i>Nova Tarefa
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formTarefa">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Descrição da Tarefa</label>
                                <textarea class="form-control" name="descricao" rows="3" required></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Prioridade</label>
                                    <select class="form-select" name="prioridade">
                                        <option value="Baixa">Baixa</option>
                                        <option value="Média" selected>Média</option>
                                        <option value="Alta">Alta</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Data Limite</label>
                                    <input type="date" class="form-control" name="data_limite">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tempo Estimado (min)</label>
                                    <input type="number" class="form-control" name="tempo_estimado" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Salvar Tarefa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Editar Rotina Fixa -->
        <div class="modal fade" id="modalEditarRotinaFixa" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Rotina Fixa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formEditarRotinaFixa">
                        <input type="hidden" id="editRotinaFixaId" name="id">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="editNomeRotinaFixa" class="form-label">Nome da Rotina</label>
                                <input type="text" class="form-control" id="editNomeRotinaFixa" name="nome" required>
                            </div>
                            <div class="mb-3">
                                <label for="editHorarioRotinaFixa" class="form-label">Horário Sugerido</label>
                                <input type="time" class="form-control" id="editHorarioRotinaFixa" name="horario_sugerido">
                            </div>
                            <div class="mb-3">
                                <label for="editDescricaoRotinaFixa" class="form-label">Descrição</label>
                                <textarea class="form-control" id="editDescricaoRotinaFixa" name="descricao" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Editar Rotina Diária -->
        <div class="modal fade" id="modalEditarRotinaDiaria" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Rotina Diária</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formEditarRotinaDiaria">
                        <input type="hidden" id="editRotinaDiariaId" name="id">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="editNomeRotinaDiaria" class="form-label">Nome da Rotina</label>
                                <input type="text" class="form-control" id="editNomeRotinaDiaria" name="nome" required>
                            </div>
                            <div class="mb-3">
                                <label for="editHorarioRotinaDiaria" class="form-label">Horário Sugerido</label>
                                <input type="time" class="form-control" id="editHorarioRotinaDiaria" name="horario_sugerido">
                            </div>
                            <div class="mb-3">
                                <label for="editDescricaoRotinaDiaria" class="form-label">Descrição</label>
                                <textarea class="form-control" id="editDescricaoRotinaDiaria" name="descricao" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Editar Tarefa -->
        <div class="modal fade" id="modalEditarTarefa" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Tarefa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="formEditarTarefa">
                        <input type="hidden" id="editTarefaId" name="id">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="editTituloTarefa" class="form-label">Título</label>
                                <input type="text" class="form-control" id="editTituloTarefa" name="titulo" required>
                            </div>
                            <div class="mb-3">
                                <label for="editDescricaoTarefa" class="form-label">Descrição</label>
                                <textarea class="form-control" id="editDescricaoTarefa" name="descricao" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editPrioridadeTarefa" class="form-label">Prioridade</label>
                                    <select class="form-select" id="editPrioridadeTarefa" name="prioridade" required>
                                        <option value="Baixa">Baixa</option>
                                        <option value="Média">Média</option>
                                        <option value="Alta">Alta</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="editDataTarefa" class="form-label">Data de Vencimento</label>
                                    <input type="date" class="form-control" id="editDataTarefa" name="data_vencimento">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Estatísticas -->
        <div class="modal fade" id="modalEstatisticas" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-graph-up me-2"></i>Estatísticas Detalhadas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="stat-card-large">
                                    <h6>Rotinas Fixas</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $estatisticas['rotinas_fixas']['percentual']; ?>%"></div>
                                    </div>
                                    <small><?php echo $estatisticas['rotinas_fixas']['concluidas']; ?>/<?php echo $estatisticas['rotinas_fixas']['total']; ?> concluídas</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card-large">
                                    <h6>Rotinas Diárias</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $estatisticas['rotinas_diarias']['percentual']; ?>%"></div>
                                    </div>
                                    <small><?php echo $estatisticas['rotinas_diarias']['concluidas']; ?>/<?php echo $estatisticas['rotinas_diarias']['total']; ?> concluídas</small>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="stat-card-large">
                                    <h6>Tarefas Pendentes</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $estatisticas['tarefas']['percentual']; ?>%"></div>
                                    </div>
                                    <small><?php echo $estatisticas['tarefas']['pendentes']; ?> pendentes</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card-large">
                                    <h6>Produtividade Geral</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $estatisticas['produtividade']; ?>%"></div>
                                    </div>
                                    <small><?php echo $estatisticas['produtividade']; ?>% de produtividade</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            // ===== SISTEMA DE NOTIFICAÇÕES =====
            function showToast(title, message, type = 'success') {
                const toastContainer = document.querySelector('.toast-container');
                const toastId = 'toast-' + Date.now();
                
                const toastHtml = `
                    <div id="${toastId}" class="toast fade-in-up" role="alert">
                        <div class="toast-header">
                            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} text-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} me-2"></i>
                            <strong class="me-auto">${title}</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    </div>
                `;
                
                toastContainer.insertAdjacentHTML('beforeend', toastHtml);
                
                const toastElement = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
                toast.show();
                
                toastElement.addEventListener('hidden.bs.toast', () => {
                    toastElement.remove();
                });
            }

            // ===== FUNÇÕES DOS MODAIS =====
            function abrirModalRotinaFixa() {
                const modal = new bootstrap.Modal(document.getElementById('modalRotinaFixa'));
                modal.show();
            }

            function abrirModalRotinaDiaria() {
                const modal = new bootstrap.Modal(document.getElementById('modalRotinaDiaria'));
                modal.show();
            }

            function abrirModalTarefa() {
                const modal = new bootstrap.Modal(document.getElementById('modalTarefa'));
                modal.show();
            }

            // ===== FORMULÁRIOS =====
            document.getElementById('formRotinaFixa').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('acao', 'adicionar_rotina_fixa');
                
                fetch('processar_rotina_fixa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('modalRotinaFixa')).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            });

            document.getElementById('formRotinaDiaria').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('acao', 'adicionar_rotina_diaria');
                
                fetch('processar_rotina_diaria.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('modalRotinaDiaria')).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            });

            document.getElementById('formTarefa').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('adicionar_tarefa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('modalTarefa')).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            });

            // ===== FORMULÁRIOS DE EDIÇÃO =====
            document.getElementById('formEditarRotinaFixa').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('editar_rotina_fixa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('modalEditarRotinaFixa')).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            });

            document.getElementById('formEditarRotinaDiaria').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('editar_rotina_diaria.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('modalEditarRotinaDiaria')).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            });

            document.getElementById('formEditarTarefa').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('atualizar_tarefa.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        bootstrap.Modal.getInstance(document.getElementById('modalEditarTarefa')).hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            });

            // ===== FUNÇÕES DE AÇÃO =====
            function mostrarEstatisticas() {
                const modal = new bootstrap.Modal(document.getElementById('modalEstatisticas'));
                modal.show();
            }

            function toggleRotinaFixa(id, statusAtual) {
                const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
                const acao = novoStatus === 'concluido' ? 'concluir' : 'pendente';
                
                fetch('processar_rotina_fixa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `acao=${acao}&rotina_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            }

            function toggleRotinaDiaria(id, statusAtual) {
                const novoStatus = statusAtual === 'concluido' ? 'pendente' : 'concluido';
                
                fetch('processar_rotina_diaria.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `acao=toggle&id=${id}&status=${novoStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', data.message);
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            }

            function concluirTarefa(id) {
                fetch('atualizar_status_tarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, status: 'concluida' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Sucesso!', 'Tarefa concluída!');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro de conexão', 'error');
                });
            }

            function editarRotinaFixa(id) {
                // Buscar dados da rotina
                fetch(`buscar_rotina_fixa.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Preencher modal de edição
                        document.getElementById('editNomeRotinaFixa').value = data.rotina.nome;
                        document.getElementById('editHorarioRotinaFixa').value = data.rotina.horario_sugerido || '';
                        document.getElementById('editDescricaoRotinaFixa').value = data.rotina.descricao || '';
                        document.getElementById('editRotinaFixaId').value = id;
                        
                        // Mostrar modal
                        const modal = new bootstrap.Modal(document.getElementById('modalEditarRotinaFixa'));
                        modal.show();
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro ao carregar dados da rotina', 'error');
                });
            }

            function editarRotinaDiaria(id) {
                // Buscar dados da rotina diária
                fetch(`buscar_rotina_diaria.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Preencher modal de edição
                        document.getElementById('editNomeRotinaDiaria').value = data.rotina.nome;
                        document.getElementById('editHorarioRotinaDiaria').value = data.rotina.horario_sugerido || '';
                        document.getElementById('editDescricaoRotinaDiaria').value = data.rotina.descricao || '';
                        document.getElementById('editRotinaDiariaId').value = id;
                        
                        // Mostrar modal
                        const modal = new bootstrap.Modal(document.getElementById('modalEditarRotinaDiaria'));
                        modal.show();
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro ao carregar dados da rotina', 'error');
                });
            }

            function editarTarefa(id) {
                // Buscar dados da tarefa
                fetch(`buscar_tarefa_detalhes.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Preencher modal de edição
                        document.getElementById('editTituloTarefa').value = data.tarefa.titulo;
                        document.getElementById('editDescricaoTarefa').value = data.tarefa.descricao || '';
                        document.getElementById('editPrioridadeTarefa').value = data.tarefa.prioridade;
                        document.getElementById('editDataTarefa').value = data.tarefa.data_vencimento;
                        document.getElementById('editTarefaId').value = id;
                        
                        // Mostrar modal
                        const modal = new bootstrap.Modal(document.getElementById('modalEditarTarefa'));
                        modal.show();
                    } else {
                        showToast('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showToast('Erro!', 'Erro ao carregar dados da tarefa', 'error');
                });
            }

            function excluirRotinaFixa(id, nome) {
                if (confirm(`Tem certeza que deseja excluir a rotina fixa "${nome}"?`)) {
                    fetch('excluir_rotina_fixa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Sucesso!', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showToast('Erro!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showToast('Erro!', 'Erro de conexão', 'error');
                    });
                }
            }

            function excluirRotinaDiaria(id, nome) {
                if (confirm(`Tem certeza que deseja excluir a rotina diária "${nome}"?`)) {
                    fetch('excluir_rotina_diaria.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Sucesso!', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showToast('Erro!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showToast('Erro!', 'Erro de conexão', 'error');
                    });
                }
            }

            function excluirTarefa(id, nome) {
                if (confirm(`Tem certeza que deseja excluir a tarefa "${nome}"?`)) {
                    fetch('excluir_tarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Sucesso!', data.message);
                            setTimeout(() => location.reload(), 500);
                        } else {
                            showToast('Erro!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showToast('Erro!', 'Erro de conexão', 'error');
                    });
                }
            }

            // ===== INICIALIZAÇÃO =====
            document.addEventListener('DOMContentLoaded', function() {
                // Adicionar animações aos elementos
                const elements = document.querySelectorAll('.rotina-item, .task-card, .stat-card');
                elements.forEach((element, index) => {
                    element.style.animationDelay = `${index * 0.1}s`;
                    element.classList.add('fade-in-up');
                });
            });
        </script>
    </body>
    </html>

    <?php require_once 'templates/footer.php'; ?>