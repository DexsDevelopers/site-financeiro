<?php
// includes/command_helper.php - Funções helper para processamento de comandos

/**
 * Normaliza e valida valor monetário
 */
function parseMoney(string $value): ?float {
    // Remove espaços e caracteres não numéricos exceto vírgula e ponto
    $value = trim($value);
    $value = preg_replace('/[^\d,.-]/', '', $value);
    
    // Substitui vírgula por ponto
    $value = str_replace(',', '.', $value);
    
    // Remove múltiplos pontos
    $parts = explode('.', $value);
    if (count($parts) > 2) {
        $value = $parts[0] . '.' . implode('', array_slice($parts, 1));
    }
    
    $floatValue = (float)$value;
    return $floatValue > 0 ? $floatValue : null;
}

/**
 * Sugere comando correto baseado no erro
 */
function suggestCommand(string $command, array $availableCommands): ?string {
    $command = strtolower($command);
    $suggestions = [];
    
    foreach ($availableCommands as $cmd => $aliases) {
        $aliases = is_array($aliases) ? $aliases : [$cmd];
        foreach ($aliases as $alias) {
            $similarity = similar_text($command, strtolower($alias));
            if ($similarity > 3) {
                $suggestions[$cmd] = $similarity;
            }
        }
    }
    
    if (!empty($suggestions)) {
        arsort($suggestions);
        return array_key_first($suggestions);
    }
    
    return null;
}

/**
 * Processa comando natural (ex: "gastei 100" -> !despesa 100)
 */
function parseNaturalCommand(string $text): ?array {
    $text = strtolower(trim($text));
    
    // Padrões de receita
    if (preg_match('/^(recebi|ganhei|entrou|entrada)\s+([\d,\.]+)(?:\s+(.+))?$/i', $text, $matches)) {
        return [
            'command' => '!receita',
            'value' => $matches[2],
            'description' => $matches[3] ?? 'Receita'
        ];
    }
    
    // Padrões de despesa
    if (preg_match('/^(gastei|paguei|saída|saida|despesa)\s+([\d,\.]+)(?:\s+(.+))?$/i', $text, $matches)) {
        return [
            'command' => '!despesa',
            'value' => $matches[2],
            'description' => $matches[3] ?? 'Despesa'
        ];
    }
    
    // Padrões de tarefa
    if (preg_match('/^(tarefa|fazer|lembrar|lembrete)\s+(.+)$/i', $text, $matches)) {
        return [
            'command' => '!addtarefa',
            'description' => $matches[2]
        ];
    }
    
    // Padrões de saldo
    if (preg_match('/^(saldo|quanto tenho|quanto falta|dinheiro)$/i', $text)) {
        return ['command' => '!saldo'];
    }
    
    // Padrões de tarefas
    if (preg_match('/^(tarefas|o que fazer|o que tenho|pendentes)$/i', $text)) {
        return ['command' => '!tarefas'];
    }
    
    return null;
}

/**
 * Formata resposta de ajuda contextual
 */
function formatHelpMessage(string $command, ?array $loggedUser = null): string {
    $helps = [
        '!receita' => "💰 *REGISTRAR RECEITA*\n\n" .
                     "Formato: !receita VALOR DESCRIÇÃO\n" .
                     "Exemplo: !receita 1500 Salário\n\n" .
                     "💡 Você também pode usar:\n" .
                     "• recebi 1500 Salário\n" .
                     "• ganhei 1500 Salário",
        
        '!despesa' => "💸 *REGISTRAR DESPESA*\n\n" .
                      "Formato: !despesa VALOR DESCRIÇÃO\n" .
                      "Exemplo: !despesa 50 Almoço\n\n" .
                      "💡 Você também pode usar:\n" .
                      "• gastei 50 Almoço\n" .
                      "• paguei 50 Almoço",
        
        '!saldo' => "💵 *VER SALDO*\n\n" .
                   "Mostra seu saldo do mês atual.\n\n" .
                   "Formato: !saldo\n" .
                   "Ou: saldo\n" .
                   "Ou: quanto tenho",
        
        '!tarefas' => "📋 *VER TAREFAS*\n\n" .
                     "Lista todas suas tarefas pendentes.\n\n" .
                     "Formato: !tarefas\n" .
                     "Ou: tarefas\n" .
                     "Ou: o que fazer",
        
        '!addtarefa' => "➕ *ADICIONAR TAREFA*\n\n" .
                       "Formato: !addtarefa DESCRIÇÃO [PRIORIDADE] [DATA]\n" .
                       "Exemplo: !addtarefa Estudar PHP Alta\n\n" .
                       "💡 Você também pode usar:\n" .
                       "• tarefa Estudar PHP\n" .
                       "• fazer Estudar PHP",
    ];
    
    return $helps[$command] ?? "Comando não encontrado. Digite !menu para ver todos os comandos.";
}



