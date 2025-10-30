// assets/js/onboarding-steps.js
// Mapa de tours por página – define etapas específicas por rota
(function(){
    function step(selector, title, description, side){
        return { element: selector, popover: { title: title, description: description, side: side || 'bottom' } };
    }

    function safePush(arr, exists, s){ if (exists(s.element)) arr.push(s); }

    window.ONBOARDING_STEPS = {
        'dashboard.php': function(exists, isMobile){
            const steps = [];
            safePush(steps, exists, step('#btn-toggle-saldo', 'Ocultar/Mostrar valores', 'Clique para ocultar valores sensíveis do dashboard.', 'right'));
            safePush(steps, exists, step('#formIaRapida', 'Lançamento Rápido com IA', 'Digite algo como: "Comprei pizza por R$ 25 hoje" e a IA entende tudo.', isMobile ? 'top' : 'bottom'));
            safePush(steps, exists, step('#pieChart', 'Despesas por Categoria', 'Veja suas despesas agrupadas por categoria.', 'left'));
            safePush(steps, exists, step('#barChart', 'Despesas Diárias', 'Acompanhe a evolução diária dos seus gastos.', 'left'));
            safePush(steps, exists, step('a[href="tarefas.php"]', 'Tarefas', 'Acesse a tela de tarefas para gerenciar sua rotina.', 'left'));
            safePush(steps, exists, step('a[href="extrato_completo.php"]', 'Extrato Completo', 'Visualize o extrato com todos os lançamentos.', 'left'));
            return steps;
        },
        'tarefas.php': function(exists, isMobile){
            const steps = [];
            safePush(steps, exists, step('.btn.btn-primary, .btn.btn-danger, button[type="submit"]', 'Adicionar Tarefa', 'Crie novas tarefas rapidamente.', 'right'));
            safePush(steps, exists, step('.task-list, #lista-tarefas-pendentes', 'Lista de Tarefas', 'Arraste, conclua e edite suas tarefas.', 'top'));
            safePush(steps, exists, step('.subtask-list, .subtasks-container', 'Subtarefas', 'Gerencie subtarefas: concluir, editar e excluir.', 'top'));
            safePush(steps, exists, step('button[data-bs-target^="#subtarefas-"], .btn-outline-secondary', 'Detalhes/Subtarefas', 'Expanda para ver detalhes e subtarefas.', 'right'));
            return steps;
        },
        'calendario.php': function(exists){
            const steps = [];
            safePush(steps, exists, step('#calendar, .calendar', 'Calendário', 'Veja suas tarefas e eventos no calendário.', 'bottom'));
            safePush(steps, exists, step('.btn.btn-danger, .btn.btn-primary', 'Novo Evento', 'Adicione rapidamente um novo evento.', 'right'));
            return steps;
        },
        'relatorios.php': function(exists){
            const steps = [];
            safePush(steps, exists, step('.filters, form[action*="relatorios"]', 'Filtros', 'Selecione período e filtros para gerar relatórios.', 'right'));
            safePush(steps, exists, step('canvas, .chart-container', 'Gráficos', 'Analise seus resultados em gráficos interativos.', 'left'));
            return steps;
        },
        'categorias.php': function(exists){
            const steps = [];
            safePush(steps, exists, step('form[action*="criar_categoria"], .btn.btn-danger, .btn.btn-primary', 'Criar Categoria', 'Defina novas categorias para organizar suas finanças.', 'right'));
            safePush(steps, exists, step('table, .list-group', 'Categorias', 'Edite, reordene e exclua categorias existentes.', 'top'));
            return steps;
        },
        'perfil.php': function(exists){
            const steps = [];
            safePush(steps, exists, step('form[action*="atualizar_perfil"], #perfil, .card', 'Perfil', 'Atualize suas informações pessoais com segurança.', 'bottom'));
            safePush(steps, exists, step('button[type="submit"], .btn.btn-danger, .btn.btn-primary', 'Salvar Alterações', 'Salve qualquer atualização realizada.', 'right'));
            return steps;
        }
    };
})();


