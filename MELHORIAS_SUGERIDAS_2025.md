# 📈 Melhorias Sugeridas para o Sistema de Tarefas - 2025

## 🎯 Matriz de Prioridade

| Prioridade | Impacto | Esforço | Recomendação |
|-----------|---------|--------|--------------|
| **CRÍTICA** | Alto | Baixo | ✅ Implementar AGORA |
| **ALTA** | Alto | Médio | 🔴 Próximas sprints |
| **MÉDIA** | Médio | Médio | 🟡 Backlog |
| **BAIXA** | Baixo | Alto | 🔵 Futuro |

---

## 🔴 CRÍTICAS (Implementar Imediatamente)

### 1. **Listar Tarefas Concluídas**
**Problema:** Usuário não consegue ver o que foi concluído
**Impacto:** Perda de visibilidade histórica
**Esforço:** Baixo (1h)

```php
// Adicionar seção Tarefas Concluídas em tarefas.php
$stmt = $pdo->prepare("
    SELECT id, descricao, prioridade, data_limite, data_criacao
    FROM tarefas 
    WHERE id_usuario = ? AND status = 'concluido'
    ORDER BY data_criacao DESC
    LIMIT 50
");
```

**Benefício:** Usuário vê histórico de realizações ✅

---

### 2. **Expiração de Tarefas Atrasadas**
**Problema:** Tarefas antigas aparecem como pendentes
**Impacto:** Lista bagunçada com itens mortos
**Esforço:** Médio (2h)

```javascript
// Adicionar badge "ATRASADO" em tarefas com data_limite < hoje
const isAtrasada = new Date(tarefa.data_limite) < new Date();
if (isAtrasada) {
    badge.textContent = '🔴 ATRASADO';
    item.style.borderLeft = '3px solid #dc3545';
}
```

**Benefício:** Visualizar pendências críticas 🎯

---

### 3. **Contador Total de Tarefas no Header**
**Problema:** Stats não mostram visão completa
**Impacto:** Falta contexto geral
**Esforço:** Baixo (30min)

```php
// Em tarefas.php, adicionar:
$stmt_total = $pdo->prepare("SELECT COUNT(*) as total FROM tarefas WHERE id_usuario = ?");
$stmt_total->execute([$userId]);
$total_tarefas = $stmt_total->fetch()['total'];
```

**Benefício:** Dashboard com informações completas 📊

---

### 4. **Sincronização de Botão Nova Tarefa**
**Problema:** Modal nova tarefa está quebrado após algumas ações
**Impacto:** Usuário não consegue adicionar mais tarefas
**Esforço:** Baixo (1h)

```javascript
// Usar event delegation ao invés de addEventListener direto
document.addEventListener('click', (e) => {
    if (e.target.closest('.btn-nova-tarefa')) {
        abrirModalTarefa();
    }
});
```

**Benefício:** Modal sempre funciona ✅

---

## 🟠 ALTAS (Próximas Sprints)

### 5. **Drag & Drop para Ordenar Tarefas**
**Problema:** Usuário não consegue reordenar prioridades
**Impacto:** Melhor organização pessoal
**Esforço:** Alto (4h)

```javascript
// Implementar com HTML5 Drag API ou SortableJS
tarefaItem.draggable = true;
tarefaItem.addEventListener('dragstart', (e) => {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this);
});
```

**Benefício:** Reorganizar tarefas por importância 🔄

---

### 6. **Categorias/Tags para Tarefas**
**Problema:** Todas as tarefas na mesma lista
**Impacto:** Difícil organizar por contexto
**Esforço:** Médio (3h)

**Banco de Dados:**
```sql
CREATE TABLE tarefas_categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT,
    nome VARCHAR(50),
    cor VARCHAR(7),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

ALTER TABLE tarefas ADD COLUMN id_categoria INT;
```

**Benefício:** Agrupar por Trabalho/Pessoal/Saúde 📂

---

### 7. **Notificações de Tarefas Atrasadas**
**Problema:** Usuário não é lembrado de tarefas pendentes
**Impacto:** Esquecimento de prazos
**Esforço:** Médio (3h)

```javascript
// Notificação nativa do browser
if (Notification.permission === 'granted') {
    new Notification('Tarefa atrasada!', {
        body: 'Você tem tarefas pendentes',
        icon: '/logo.png'
    });
}
```

**Benefício:** Lembrete automático ⏰

---

### 8. **Modo Dark/Light Toggle**
**Problema:** Tema escuro pode ser pesado para alguns
**Impacto:** Melhor acessibilidade
**Esforço:** Médio (2h)

```javascript
// Adicionar botão toggle no header
const isDark = localStorage.getItem('theme') === 'dark';
document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');

// CSS com variáveis
:root[data-theme="light"] {
    --bg: #ffffff;
    --text: #000000;
}
```

**Benefício:** Reduz fadiga ocular em dias claros 👁️

---

### 9. **Atalhos de Teclado Globais**
**Problema:** Usuário precisa mexer no mouse para tudo
**Impacto:** Mais rápido para power users
**Esforço:** Médio (2h)

```javascript
// Alt+N = Nova Tarefa
// Alt+R = Nova Rotina
// Ctrl+L = Buscar
// Delete = Deletar selecionado
document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 'l') {
        document.getElementById('searchInput').focus();
    }
});
```

**Benefício:** 50% mais rápido para navegar ⚡

---

### 10. **Export/Import de Tarefas (CSV)**
**Problema:** Dados presos na aplicação
**Impacto:** Backup e portabilidade
**Esforço:** Alto (4h)

```php
// export_tarefas.php
$csv = "Descrição,Prioridade,Data Limite,Status\n";
foreach ($tarefas as $t) {
    $csv .= "{$t['descricao']},{$t['prioridade']},{$t['data_limite']},{$t['status']}\n";
}
header('Content-Type: text/csv; charset=utf-8');
echo $csv;
```

**Benefício:** Portabilidade dos dados 📤

---

## 🟡 MÉDIAS (Backlog)

### 11. **Relatório de Produtividade**
**Problema:** Usuário não vê padrão de trabalho
**Impacto:** Autoconsciência e melhor planejamento
**Esforço:** Alto (5h)

```php
// Gráfico de tarefas completadas por semana
$stmt = $pdo->prepare("
    SELECT WEEK(data_conclusao) as semana, COUNT(*) as total
    FROM tarefas 
    WHERE id_usuario = ? AND status = 'concluido'
    GROUP BY WEEK(data_conclusao)
");
```

**Benefício:** Visualizar progresso 📈

---

### 12. **Estimativa de Tempo por Tarefa**
**Problema:** Usuário não sabe quanto tempo vai levar
**Impacto:** Melhor planejamento diário
**Esforço:** Médio (2h)

```sql
ALTER TABLE tarefas ADD COLUMN tempo_estimado_minutos INT DEFAULT 0;
ALTER TABLE tarefas ADD COLUMN tempo_gasto_minutos INT DEFAULT 0;
```

**Benefício:** Timeboxing efetivo ⏱️

---

### 13. **Recorrência de Tarefas**
**Problema:** Tarefas repetitivas precisam ser recriadas manualmente
**Impacto:** Menos cliques, mais produtivo
**Esforço:** Alto (4h)

```sql
ALTER TABLE tarefas ADD COLUMN recorrencia ENUM('nunca', 'diaria', 'semanal', 'mensal');
ALTER TABLE tarefas ADD COLUMN proxima_data_recorrencia DATE;
```

**Benefício:** Tarefas se criam automaticamente 🔄

---

### 14. **Comentários/Notas em Tarefas**
**Problema:** Usuário não consegue adicionar contexto
**Impacto:** Melhor compreensão de tarefas complexas
**Esforço:** Médio (3h)

```sql
CREATE TABLE tarefas_comentarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_tarefa INT,
    conteudo TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Benefício:** Histórico de mudanças e decisões 💬

---

### 15. **Vinculação Entre Tarefas (Dependências)**
**Problema:** Não há forma de marcar que Tarefa B depende de Tarefa A
**Impacto:** Melhor sequência de trabalho
**Esforço:** Alto (5h)

```sql
CREATE TABLE tarefas_dependencias (
    id_tarefa_principal INT,
    id_tarefa_dependente INT,
    FOREIGN KEY (id_tarefa_principal) REFERENCES tarefas(id),
    FOREIGN KEY (id_tarefa_dependente) REFERENCES tarefas(id)
);
```

**Benefício:** Visualizar fluxo de trabalho 🔗

---

## 🔵 BAIXAS (Futuro)

### 16. **Integração com Google Calendar**
**Problema:** Tarefas não sincronizam com calendário
**Impacto:** Visão unificada
**Esforço:** Muito Alto (10h)

**Benefício:** Calendário sempre atualizado 📅

---

### 17. **IA para Sugerir Prioridades**
**Problema:** Usuário indeciso sobre o que fazer primeiro
**Impacto:** Tomada de decisão mais rápida
**Esforço:** Muito Alto (8h)

**Benefício:** Sugestão inteligente 🤖

---

### 18. **Modo Pomodoro Integrado**
**Problema:** Usuário não consegue cronometrar tarefas
**Impacto:** Melhor focus e produtividade
**Esforço:** Médio (3h)

```javascript
// Timer de 25min com pausa de 5min
let pomodoroTime = 25 * 60; // segundos
setInterval(() => {
    pomodoroTime--;
    updateUI();
}, 1000);
```

**Benefício:** Técnica Pomodoro nativa ⏱️

---

### 19. **Compartilhamento de Tarefas (Multi-usuário)**
**Problema:** Tarefas são pessoais apenas
**Impacto:** Colaboração em equipe
**Esforço:** Muito Alto (12h)

**Benefício:** Trabalho em equipe 👥

---

### 20. **Mobile App (PWA)**
**Problema:** Usuário quer usar no telefone
**Impacto:** Acessibilidade total
**Esforço:** Muito Alto (20h)

**Benefício:** App offline funcional 📱

---

---

## 🚀 Roadmap Recomendado

### **Fase 1 (SEMANA 1)** - Críticas
- [ ] Listar Tarefas Concluídas
- [ ] Expiração/Atraso de Tarefas
- [ ] Contador Total no Header

### **Fase 2 (SEMANA 2-3)** - Altas
- [ ] Drag & Drop
- [ ] Tags/Categorias
- [ ] Notificações

### **Fase 3 (SEMANA 4)** - Médias
- [ ] Relatório de Produtividade
- [ ] Estimativa de Tempo
- [ ] Recorrência

### **Fase 4 (FUTURO)** - Baixas
- [ ] IA, Pomodoro, Multi-usuário, PWA

---

## 📊 Impacto Esperado

| Melhoria | Produtividade | Satisfação | Tempo Dev |
|----------|---------------|-----------|-----------|
| 1-3 Críticas | +40% | +30% | 2.5h |
| 5-9 Altas | +25% | +35% | 15h |
| 11-15 Médias | +15% | +25% | 20h |

---

## 💡 Quick Wins (1-2 horas cada)

1. ✅ Contador total de tarefas
2. ✅ Badge "ATRASADO" em vermelha
3. ✅ Botão "Limpar Concluídas"
4. ✅ Atalho Ctrl+L para busca
5. ✅ Animação ao completar tarefa

**Total:** 5-10 horas = 40% melhoria ⚡

---

## 🎯 Próximas Ações

1. **Hoje:** Implementar as 3 Críticas
2. **Esta semana:** Iniciar 1-2 Altas
3. **Próxima semana:** Avaliar feedback e priorizar

---

**Gerado em:** 2025-10-17
**Versão:** 2.0
**Status:** Recomendações Prontas para Implementação ✅
