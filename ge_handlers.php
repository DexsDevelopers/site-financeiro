<?php
// ge_handlers.php - Processador de requisições AJAX para Gestão de Empresas

session_start();
require_once __DIR__ . '/includes/db_connect.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

$userId = !empty($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : (int) ($_SESSION['user_id'] ?? 0);
$acao = $_REQUEST['acao'] ?? '';

header('Content-Type: application/json');

switch ($acao) {
    case 'get_empresa':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM ge_empresas WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        $empresa = $stmt->fetch();
        
        if ($empresa) {
            echo json_encode(['success' => true, 'data' => $empresa]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Empresa não encontrada.']);
        }
        break;

    case 'criar_empresa':
        $nome = $_POST['nome'] ?? '';
        $segmento = $_POST['segmento'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $idPai = isset($_POST['id_pai']) ? (int)$_POST['id_pai'] : null;

        if (!$nome) {
            echo json_encode(['success' => false, 'message' => 'O nome da empresa é obrigatório.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO ge_empresas (id_usuario, id_pai, nome, segmento, descricao) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $idPai, $nome, $segmento, $descricao]);
            $id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Empresa cadastrada com sucesso!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
        }
        break;

    case 'get_resumo':
        $idEmpresa = (int)($_GET['id'] ?? 0);
        
        // Verificar se a empresa pertence ao usuário
        $stmtCheck = $pdo->prepare("SELECT id FROM ge_empresas WHERE id = ? AND id_usuario = ?");
        $stmtCheck->execute([$idEmpresa, $userId]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }

        // Receitas e Despesas do mês atual
        $stmtFin = $pdo->prepare("SELECT 
            SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as receitas,
            SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as despesas
            FROM ge_financeiro 
            WHERE id_empresa = ? AND MONTH(data_transacao) = MONTH(CURRENT_DATE()) AND YEAR(data_transacao) = YEAR(CURRENT_DATE())");
        $stmtFin->execute([$idEmpresa]);
        $fin = $stmtFin->fetch();

        // Contagem de tarefas pendentes
        $stmtTasksCount = $pdo->prepare("SELECT COUNT(*) FROM ge_tarefas WHERE id_empresa = ? AND status = 'pendente'");
        $stmtTasksCount->execute([$idEmpresa]);
        $tasksCount = $stmtTasksCount->fetchColumn();

        // Últimas 5 tarefas
        $stmtTasks = $pdo->prepare("SELECT * FROM ge_tarefas WHERE id_empresa = ? AND status = 'pendente' ORDER BY prazo ASC, id DESC LIMIT 5");
        $stmtTasks->execute([$idEmpresa]);
        $ultimasTarefas = $stmtTasks->fetchAll();

        // Estatísticas do Grupo (se for matriz)
        $stmtSubs = $pdo->prepare("SELECT id FROM ge_empresas WHERE id_pai = ?");
        $stmtSubs->execute([$idEmpresa]);
        $subsIds = $stmtSubs->fetchAll(PDO::FETCH_COLUMN);
        
        $receitasGrupo = $fin['receitas'] ?: 0;
        $despesasGrupo = $fin['despesas'] ?: 0;
        
        if (!empty($subsIds)) {
            $in = implode(',', array_fill(0, count($subsIds), '?'));
            $stGroup = $pdo->prepare("SELECT 
                SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as receitas,
                SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as despesas
                FROM ge_financeiro 
                WHERE id_empresa IN ($in) AND MONTH(data_transacao) = MONTH(CURRENT_DATE()) AND YEAR(data_transacao) = YEAR(CURRENT_DATE())");
            $stGroup->execute($subsIds);
            $groupFin = $stGroup->fetch();
            $receitasGrupo += $groupFin['receitas'] ?: 0;
            $despesasGrupo += $groupFin['despesas'] ?: 0;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'receitas' => $fin['receitas'] ?: 0,
                'despesas' => $fin['despesas'] ?: 0,
                'saldo' => ($fin['receitas'] - $fin['despesas']),
                'receitas_grupo' => $receitasGrupo,
                'despesas_grupo' => $despesasGrupo,
                'saldo_grupo' => ($receitasGrupo - $despesasGrupo),
                'tarefas_pendentes' => $tasksCount,
                'ultimas_tarefas' => $ultimasTarefas,
                'is_matriz' => !empty($subsIds)
            ]
        ]);
        break;

    case 'get_financeiro':
        $idEmpresa = (int)($_GET['id'] ?? 0);
        
        // Transações
        $stmt = $pdo->prepare("SELECT * FROM ge_financeiro WHERE id_empresa = ? ORDER BY data_transacao DESC, id DESC LIMIT 50");
        $stmt->execute([$idEmpresa]);
        $transacoes = $stmt->fetchAll();

        // Dados para gráfico (últimos 6 meses)
        $chartData = [];
        $categoryData = [];
        
        // Categorias (PIE)
        $stmtCats = $pdo->prepare("SELECT categoria, SUM(valor) as total FROM ge_financeiro WHERE id_empresa = ? AND tipo = 'saida' GROUP BY categoria ORDER BY total DESC");
        $stmtCats->execute([$idEmpresa]);
        $categoryData = $stmtCats->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $transacoes,
            'chartData' => $chartData,
            'categoryData' => $categoryData
        ]);
        break;

    case 'adicionar_financeiro':
        $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
        $descricao = $_POST['descricao'] ?? '';
        $valor = (float)($_POST['valor'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'saida';
        $categoria = $_POST['categoria'] ?? '';
        $data = $_POST['data_transacao'] ?? date('Y-m-d');

        try {
            $stmt = $pdo->prepare("INSERT INTO ge_financeiro (id_empresa, descricao, valor, tipo, categoria, data_transacao) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$idEmpresa, $descricao, $valor, $tipo, $categoria, $data]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'excluir_financeiro':
        $id = (int)($_GET['id'] ?? 0);
        // Verificar permissão
        $stmt = $pdo->prepare("DELETE t FROM ge_financeiro t JOIN ge_empresas e ON t.id_empresa = e.id WHERE t.id = ? AND e.id_usuario = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'get_tarefas':
        $idEmpresa = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM ge_tarefas WHERE id_empresa = ? ORDER BY status DESC, prazo ASC, id DESC");
        $stmt->execute([$idEmpresa]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'adicionar_tarefa':
        $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
        $titulo = $_POST['titulo'] ?? '';
        $responsavel = $_POST['responsavel'] ?? '';
        $prazo = $_POST['prazo'] ?: null;
        $prioridade = $_POST['prioridade'] ?? 'media';

        $stmt = $pdo->prepare("INSERT INTO ge_tarefas (id_empresa, titulo, responsavel, prazo, prioridade) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$idEmpresa, $titulo, $responsavel, $prazo, $prioridade]);
        echo json_encode(['success' => true]);
        break;

    case 'atualizar_status_tarefa':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'pendente';
        $stmt = $pdo->prepare("UPDATE ge_tarefas t JOIN ge_empresas e ON t.id_empresa = e.id SET t.status = ? WHERE t.id = ? AND e.id_usuario = ?");
        $stmt->execute([$status, $id, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'excluir_tarefa':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE t FROM ge_tarefas t JOIN ge_empresas e ON t.id_empresa = e.id WHERE t.id = ? AND e.id_usuario = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'get_ideias':
        $idEmpresa = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM ge_ideias WHERE id_empresa = ? ORDER BY FIELD(status, 'andamento', 'analise', 'concluida', 'cancelada'), prioridade DESC");
        $stmt->execute([$idEmpresa]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'adicionar_ideia':
        $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
        $titulo = $_POST['titulo'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $prioridade = $_POST['prioridade'] ?? 'media';
        $notas = $_POST['notas_estrategicas'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO ge_ideias (id_empresa, titulo, descricao, prioridade, notas_estrategicas) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$idEmpresa, $titulo, $descricao, $prioridade, $notas]);
        echo json_encode(['success' => true]);
        break;

    case 'atualizar_status_ideia':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'analise';
        $stmt = $pdo->prepare("UPDATE ge_ideias t JOIN ge_empresas e ON t.id_empresa = e.id SET t.status = ? WHERE t.id = ? AND e.id_usuario = ?");
        $stmt->execute([$status, $id, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'get_conteudo':
        $idEmpresa = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM ge_conteudo WHERE id_empresa = ? ORDER BY data_publicacao ASC, id DESC");
        $stmt->execute([$idEmpresa]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'adicionar_conteudo':
        $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
        $titulo = $_POST['titulo'] ?? '';
        $plataforma = $_POST['plataforma'] ?? '';
        $data = $_POST['data_publicacao'] ?: null;
        $status = $_POST['status'] ?? 'ideia';
        $roteiro = $_POST['roteiro'] ?? '';
        $legenda = $_POST['legenda'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO ge_conteudo (id_empresa, titulo, plataforma, data_publicacao, status, roteiro, legenda) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$idEmpresa, $titulo, $plataforma, $data, $status, $roteiro, $legenda]);
        echo json_encode(['success' => true]);
        break;

    case 'get_redes_sociais':
        $idEmpresa = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM ge_redes_sociais WHERE id_empresa = ?");
        $stmt->execute([$idEmpresa]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'adicionar_rede_social':
        $idEmpresa = (int)($_POST['id_empresa'] ?? 0);
        $plataforma = $_POST['plataforma'] ?? '';
        $usuario = $_POST['usuario'] ?? '';
        $url = $_POST['url_perfil'] ?? '';
        $seguidores = (int)($_POST['seguidores'] ?? 0);

        $stmt = $pdo->prepare("INSERT INTO ge_redes_sociais (id_empresa, plataforma, usuario, url_perfil, seguidores) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$idEmpresa, $plataforma, $usuario, $url, $seguidores]);
        echo json_encode(['success' => true]);
        break;

    case 'salvar_metricas_social':
        $id = (int)($_POST['id'] ?? 0);
        $seguidores = (int)($_POST['seguidores'] ?? 0);
        $seguindo = (int)($_POST['seguindo'] ?? 0);
        $posts = (int)($_POST['posts'] ?? 0);

        try {
            $stmtUp = $pdo->prepare("UPDATE ge_redes_sociais SET seguidores = ?, seguindo = ?, posts = ? WHERE id = ?");
            $stmtUp->execute([$seguidores, $seguindo, $posts, $id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'atualizar_empresa':
        $id = (int)($_POST['id'] ?? 0);
        $nome = $_POST['nome'] ?? '';
        $cnpj = $_POST['cnpj'] ?? '';
        $segmento = $_POST['segmento'] ?? '';
        $contato = $_POST['contato'] ?? '';
        $endereco = $_POST['endereco'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $observacoes = $_POST['observacoes'] ?? '';

        $stmt = $pdo->prepare("UPDATE ge_empresas SET nome = ?, cnpj = ?, segmento = ?, contato = ?, endereco = ?, descricao = ?, observacoes = ? WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$nome, $cnpj, $segmento, $contato, $endereco, $descricao, $observacoes, $id, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'excluir_empresa':
        $id = (int)($_POST['id'] ?? 0);
        // Verificar se a empresa pertence ao usuário antes de deletar
        $stmt = $pdo->prepare("DELETE FROM ge_empresas WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Empresa excluída com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Não foi possível excluir a empresa ou você não tem permissão.']);
        }
        break;

    case 'excluir_rede_social':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE t FROM ge_redes_sociais t JOIN ge_empresas e ON t.id_empresa = e.id WHERE t.id = ? AND e.id_usuario = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        break;

    case 'get_subempresas':
        $idPai = (int)($_GET['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT * FROM ge_empresas WHERE id_pai = ? AND id_usuario = ? ORDER BY nome ASC");
            $stmt->execute([$idPai, $userId]);
            $subs = $stmt->fetchAll();
            
            // Adicionar estatísticas básicas para cada subempresa
            foreach ($subs as &$sub) {
                $st = $pdo->prepare("SELECT 
                    SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as receitas,
                    SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as despesas
                    FROM ge_financeiro WHERE id_empresa = ?");
                $st->execute([$sub['id']]);
                $fin = $st->fetch();
                $sub['receitas'] = $fin['receitas'] ?: 0;
                $sub['despesas'] = $fin['despesas'] ?: 0;
                $sub['saldo'] = $sub['receitas'] - $sub['despesas'];
            }
            
            echo json_encode(['success' => true, 'data' => $subs]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
        break;
}
