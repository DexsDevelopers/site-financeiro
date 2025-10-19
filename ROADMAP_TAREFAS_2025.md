# 🗺️ Roadmap Visual - Sistema de Tarefas 2025

## 📊 Dashboard de Melhorias

```
CRÍTICAS (Implementar AGORA - 2.5h)
├─ ✅ Listar Tarefas Concluídas (1h)
├─ ✅ Badge Tarefas Atrasadas (1h)  
└─ ✅ Contador Total Header (30min)

ALTAS (Próximas 2 semanas - 15h)
├─ 🔄 Drag & Drop Ordenação (4h)
├─ 📂 Categorias/Tags (3h)
├─ 🔔 Notificações (3h)
└─ 💾 Export CSV (4h)

MÉDIAS (Backlog - 20h)
├─ 📈 Relatório Produtividade (5h)
├─ ⏱️ Estimativa Tempo (2h)
├─ 🔄 Recorrência Tarefas (4h)
├─ 💬 Comentários (3h)
└─ 🔗 Dependências (5h)

BAIXAS (Futuro - 40h+)
├─ 📅 Google Calendar (10h)
├─ 🤖 IA Sugestões (8h)
├─ ⏲️ Pomodoro (3h)
├─ 👥 Multi-usuário (12h)
└─ 📱 PWA App (20h)
```

---

## 🎯 Quick Wins (Comece Aqui!)

### Dia 1 - Morning (1h)
```
✅ Adicionar seção "Tarefas Concluídas"
   └─ SELECT * WHERE status = 'concluido'
   └─ Colar código abaixo em tarefas.php

✅ Adicionar "Badge ATRASADO"
   └─ Se data_limite < hoje → badge vermelha
```

**Código Quick Win #1 - Tarefas Concluídas:**
```php
// Em tarefas.php, após seção Tarefas Pendentes, adicionar:

<!-- TAREFAS CONCLUÍDAS -->
<div class="section">
    <div class="section-title">
        <i class="bi bi-check-circle"></i>
        Tarefas Concluídas
    </div>
    
    <div class="items-list">
        <?php
        $stmtConcluidas = $pdo->prepare("
            SELECT id, descricao, prioridade, data_limite
            FROM tarefas 
            WHERE id_usuario = ? AND status = 'concluido'
            ORDER BY id DESC
            LIMIT 30
        ");
        $stmtConcluidas->execute([$userId]);
        $tarefasConcluidas = $stmtConcluidas->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <?php if (empty($tarefasConcluidas)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎉</div>
                <p>Nenhuma tarefa concluída ainda</p>
            </div>
        <?php else: ?>
            <?php foreach ($tarefasConcluidas as $task): ?>
                <div class="item" style="opacity: 0.7;">
                    <i class="bi bi-check-circle" style="color: #6bcf7f;"></i>
                    <div class="item-content">
                        <div class="item-title" style="text-decoration: line-through;">
                            <?php echo htmlspecialchars($task['descricao']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
```

---

### Dia 1 - Afternoon (1h)
```
✅ Melhorar Badge de Prioridade com Alertas
   └─ Tarefas ATRASADAS ficam vermelhas
   └─ Animar pulsação se for crítica

✅ Melhorar Stats com Total Geral
   └─ Adicionar card: Total Tarefas: X
```

**Código Quick Win #2 - Badge Atrasado:**
```javascript
// Adicionar em assets/js/tarefas-novo.js, dentro de DOMContentLoaded:

document.querySelectorAll('[data-task-id]').forEach(item => {
    const dataLimite = item.dataset.dataLimite;
    if (dataLimite && new Date(dataLimite) < new Date()) {
        // Tarefa atrasada
        item.style.borderLeft = '4px solid #dc3545';
        const badge = document.createElement('span');
        badge.className = 'badge badge-alta';
        badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ATRASADO';
        item.querySelector('.item-meta').prepend(badge);
    }
});
```

---

### Dia 2 (1h)
```
✅ Adicionar Botão "Limpar Concluídas"
   └─ DELETE FROM tarefas WHERE status='concluido' AND id_usuario=?

✅ Animação ao Marcar Completa
   └─ Fade out + slideUp quando clica checkbox
```

**Código Quick Win #3 - Limpar Concluídas:**
```html
<!-- Adicionar no header, próximo aos botões de ação -->
<button class="btn" style="background: #888;" onclick="limparConcluidas()" title="Limpar">
    <i class="bi bi-trash"></i> Limpar Concluídas
</button>

<script>
function limparConcluidas() {
    if (!confirm('Deletar todas as tarefas concluídas?')) return;
    
    fetch('limpar_tarefas_concluidas.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`✅ ${data.deletadas} tarefas deletadas`);
                location.reload();
            }
        });
}
</script>
```

**Arquivo novo: `limpar_tarefas_concluidas.php`**
```php
<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

require_once 'includes/db_connect.php';

try {
    $stmt = $pdo->prepare("DELETE FROM tarefas WHERE id_usuario = ? AND status = 'concluido'");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'deletadas' => $stmt->rowCount()
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
```

---

## 🔥 Next Level (3-5 horas)

### Feature #1: Drag & Drop
```javascript
// assets/js/drag-drop.js (NOVO)
let dragSource = null;

document.addEventListener('dragstart', (e) => {
    if (e.target.classList.contains('item')) {
        dragSource = e.target;
        e.dataTransfer.effectAllowed = 'move';
    }
});

document.addEventListener('drop', (e) => {
    e.preventDefault();
    const targetItem = e.target.closest('.item');
    if (targetItem && dragSource && targetItem !== dragSource) {
        // Reordenar visualmente
        dragSource.parentNode.insertBefore(dragSource, targetItem);
        // Salvar ordem no backend
        salvarOr emTarefas();
    }
});
```

### Feature #2: Categorias/Tags
```sql
-- Script de migração
ALTER TABLE tarefas ADD COLUMN id_categoria INT;

CREATE TABLE tarefas_categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    nome VARCHAR(50),
    cor VARCHAR(7),
    icone VARCHAR(50),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    UNIQUE KEY (id_usuario, nome)
);

INSERT INTO tarefas_categorias (id_usuario, nome, cor, icone) VALUES
(1, 'Trabalho', '#dc3545', 'briefcase'),
(1, 'Pessoal', '#6bcf7f', 'heart'),
(1, 'Saúde', '#ffd93d', 'activity'),
(1, 'Aprendizado', '#5b9ef7', 'book');
```

---

## 📈 Impacto por Fase

```
ANTES (Atual)
├─ Tarefas pendentes apenas
├─ Sem visualização de concluídas
├─ Sem alertas de atraso
└─ Sem organização por categoria

DEPOIS (Quick Wins)
├─ +30% visibilidade de tarefas
├─ Histórico de realizações
├─ Alertas visuais de atraso
└─ Organização intuitiva

DEPOIS (Next Level)
├─ +50% produtividade
├─ Reordenação por importância
├─ Categorização contextual
└─ Integração com calendário
```

---

## 🎬 Começar Agora

### Passo 1: Copiar Quick Win #1 (30min)
```bash
# 1. Abrir tarefas.php
# 2. Procurar: <!-- TAREFAS -->
# 3. Adicionar DEPOIS dessa seção: código de Tarefas Concluídas
# 4. Salvar e testar
```

### Passo 2: Copiar Quick Win #2 (20min)
```bash
# 1. Abrir assets/js/tarefas-novo.js
# 2. Adicionar ao final do DOMContentLoaded: verificar datas atrasadas
# 3. Salvar e testar
```

### Passo 3: Deploy
```bash
git add -A
git commit -m "Quick wins: Tarefas concluídas + Alertas atrasados"
git push origin main
```

**Resultado:** 3 horas = +30% visibilidade ⚡

---

## 💡 Recomendação Pessoal

### Para Hoje
✅ **Implementar Quick Wins** (3h) - Alto impacto, baixo esforço

### Para Esta Semana  
✅ **Drag & Drop** (4h) - Melhora UX significativamente
✅ **Botão Limpar** (1h) - Power user feature

### Para Próxima Semana
✅ **Categorias** (3h) - Escala bem para muitas tarefas
✅ **Notificações** (2h) - Retenção de usuário

---

## 🎯 Checklist de Implementação

### Quick Wins
- [ ] Seção Tarefas Concluídas (1h)
- [ ] Badge Atrasadas (1h)
- [ ] Botão Limpar (1h)
- [ ] Deploy (10min)

### Phase 2 (Próxima)
- [ ] Drag & Drop (4h)
- [ ] Animações (1h)
- [ ] Testes (1h)
- [ ] Deploy (10min)

### Phase 3 (Backlog)
- [ ] Categorias (3h)
- [ ] Notificações (2h)
- [ ] Relatórios (5h)

---

**Status:** Pronto para começar 🚀
**Tempo Estimado Quick Wins:** 3-4 horas
**ROI esperado:** +30% usabilidade
