<?php
// includes/push_eventos.php
// Dispatcher centralizado de push por evento do sistema

require_once __DIR__ . '/push_helper.php';

/**
 * Dispara push baseado em evento do sistema.
 * Só envia se o usuário tiver ao menos 1 dispositivo inscrito.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $evento  'nova_despesa' | 'nova_receita' | 'check_orcamento' | 'tarefa_urgente' | 'tarefa_concluida'
 * @param array  $dados   Dados do evento
 */
function dispararPushEvento($pdo, $userId, $evento, $dados = []) {
    try {
        // Verifica se o usuário tem dispositivos inscritos (evita trabalho desnecessário)
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?");
        $stmtCheck->execute([$userId]);
        if ((int)$stmtCheck->fetchColumn() === 0) return;
    } catch (Exception $e) {
        return; // Tabela pode não existir ainda
    }

    switch ($evento) {

        // ── DESPESA ALTA ──────────────────────────────────────────────────────
        case 'nova_despesa':
            $valor     = (float)($dados['valor']     ?? 0);
            $categoria = $dados['categoria'] ?? 'Geral';
            $descricao = $dados['descricao'] ?? '';

            if ($valor < 100) {
                // Mesmo abaixo de R$100, verifica orçamento
                dispararPushEvento($pdo, $userId, 'check_orcamento');
                return;
            }

            $valorFmt = 'R$ ' . number_format($valor, 2, ',', '.');
            $title = '💸 Gasto Registrado';
            $body  = "{$valorFmt} em {$categoria}";
            if ($descricao) $body .= " — {$descricao}";

            sendWebPush($pdo, $userId, $title, $body, 'extrato_completo.php', ['tipo' => 'alerta']);

            // Depois verifica orçamento também
            dispararPushEvento($pdo, $userId, 'check_orcamento');
            break;

        // ── RECEITA GRANDE ────────────────────────────────────────────────────
        case 'nova_receita':
            $valor     = (float)($dados['valor']     ?? 0);
            $descricao = $dados['descricao'] ?? '';

            if ($valor < 500) return; // Só notifica receitas expressivas

            $valorFmt = 'R$ ' . number_format($valor, 2, ',', '.');
            $title = '💰 Receita Registrada!';
            $body  = "+ {$valorFmt}" . ($descricao ? " — {$descricao}" : '');

            sendWebPush($pdo, $userId, $title, $body, 'extrato_completo.php', ['tipo' => 'sucesso']);
            break;

        // ── VERIFICAÇÃO DE ORÇAMENTO ──────────────────────────────────────────
        case 'check_orcamento':
            $mes = date('m');
            $ano = date('Y');

            // Total gasto no mês
            $stmtGasto = $pdo->prepare(
                "SELECT COALESCE(SUM(valor), 0) FROM transacoes
                 WHERE id_usuario = ? AND tipo = 'despesa'
                   AND MONTH(data_transacao) = ? AND YEAR(data_transacao) = ?"
            );
            $stmtGasto->execute([$userId, $mes, $ano]);
            $totalDespesas = (float)$stmtGasto->fetchColumn();

            // Orçamento definido (tenta tabela orcamentos)
            try {
                $stmtOrc = $pdo->prepare(
                    "SELECT valor_limite FROM orcamentos
                     WHERE id_usuario = ? AND mes = ? AND ano = ? LIMIT 1"
                );
                $stmtOrc->execute([$userId, $mes, $ano]);
                $orcamento = (float)$stmtOrc->fetchColumn();
            } catch (Exception $e) {
                return; // Tabela pode não existir
            }

            if (!$orcamento) return;

            $percentual = ($totalDespesas / $orcamento) * 100;

            // Evita spam: só avisa exatamente nos limiares (80% e 100%)
            // Usa uma flag simples baseada no valor inteiro de percentual para não spammar
            $limiar = $percentual >= 100 ? 100 : ($percentual >= 80 ? 80 : 0);
            if ($limiar === 0) return;

            // Verifica se já avisou neste limiar hoje
            try {
                $stmtAviso = $pdo->prepare(
                    "SELECT COUNT(*) FROM notificacoes_historico
                     WHERE user_id = ? AND DATE(created_at) = CURDATE()
                       AND titulo LIKE '%Orçamento%' AND titulo LIKE ?  LIMIT 1"
                );
                $stmtAviso->execute([$userId, "%{$limiar}%%"]);
                if ((int)$stmtAviso->fetchColumn() > 0) return; // Já avisou hoje
            } catch (Exception $e) { /* ignora */ }

            $valorFmt   = 'R$ ' . number_format($totalDespesas, 2, ',', '.');
            $orcFmt     = 'R$ ' . number_format($orcamento,     2, ',', '.');
            $percFmt    = number_format($percentual, 0);

            if ($limiar >= 100) {
                $title = '🚨 Orçamento Estourado!';
                $body  = "Você gastou {$valorFmt} de {$orcFmt} este mês.";
            } else {
                $title = "⚠️ {$percFmt}% do Orçamento Usado";
                $body  = "Já gastou {$valorFmt} de {$orcFmt} este mês. Cuidado!";
            }

            sendWebPush($pdo, $userId, $title, $body, 'orcamento.php', ['tipo' => 'alerta']);
            break;

        // ── TAREFA COM PRAZO URGENTE ──────────────────────────────────────────
        case 'tarefa_urgente':
            $descricao   = $dados['descricao']   ?? 'Tarefa';
            $data_limite = $dados['data_limite']  ?? null;

            if (!$data_limite) return;

            $hoje   = date('Y-m-d');
            $amanha = date('Y-m-d', strtotime('+1 day'));

            if ($data_limite === $hoje) {
                $title = '⏰ Tarefa vence HOJE!';
                $body  = "\"{$descricao}\" — não esqueça de concluir hoje!";
            } elseif ($data_limite === $amanha) {
                $title = '📋 Tarefa vence amanhã';
                $body  = "\"{$descricao}\" — prazo é amanhã!";
            } else {
                return;
            }

            sendWebPush($pdo, $userId, $title, $body, 'tarefas.php', ['tipo' => 'lembrete']);
            break;

        // ── TAREFA CONCLUÍDA ──────────────────────────────────────────────────
        case 'tarefa_concluida':
            $descricao = $dados['descricao'] ?? 'Tarefa';

            // Quantas concluiu hoje
            try {
                $stmtHoje = $pdo->prepare(
                    "SELECT COUNT(*) FROM tarefas
                     WHERE id_usuario = ? AND status = 'concluida'
                       AND DATE(data_conclusao) = CURDATE()"
                );
                $stmtHoje->execute([$userId]);
                $concluidasHoje = (int)$stmtHoje->fetchColumn();
            } catch (Exception $e) {
                $concluidasHoje = 1;
            }

            if ($concluidasHoje >= 5) {
                $title = "🏆 {$concluidasHoje} tarefas hoje!";
                $body  = "\"{$descricao}\" concluída. Dia incrível de produtividade!";
            } elseif ($concluidasHoje >= 3) {
                $title = "🔥 {$concluidasHoje} tarefas hoje!";
                $body  = "\"{$descricao}\" concluída. Continue assim!";
            } else {
                $title = '✅ Tarefa Concluída!';
                $body  = "\"{$descricao}\" finalizada. Bom trabalho!";
            }

            sendWebPush($pdo, $userId, $title, $body, 'tarefas.php', ['tipo' => 'sucesso']);
            break;
    }
}
