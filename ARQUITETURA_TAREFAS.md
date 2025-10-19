# 🏗️ Arquitetura Modularizada - Sistema de Tarefas

## 📁 Estrutura de Arquivos

```
📦 site-financeiro/
├── 📄 tarefas.php                      # MAIN: HTML + Lógica de dados
├── 
├── 📁 includes/
│   └── 📄 tarefas_queries.php          # ✨ NOVO: Funções de BD
│
├── 📁 assets/
│   ├── 📁 css/
│   │   └── 📄 tarefas.css              # ✨ NOVO: Estilos
│   └── 📁 js/
│       └── 📄 tarefas.js               # ✨ NOVO: Lógica JS
│
├── 📄 adicionar_tarefa.php
├── 📄 obter_tarefa.php
├── 📄 atualizar_tarefa.php
├── 📄 excluir_tarefa.php
├── 📄 concluir_tarefa_ajax.php
├── 📄 adicionar_subtarefa.php
├── 📄 atualizar_subtarefa_status.php
├── 📄 deletar_subtarefa.php
├── 📄 adicionar_rotina_fixa.php
├── 📄 processar_rotina_diaria.php
├── 📄 editar_rotina_fixa.php
└── 📄 excluir_rotina_fixa.php
```

---

## 🎯 Responsabilidades de Cada Arquivo

### **1. `tarefas.php` (MAIN)**
**Responsabilidade:** Renderização HTML + Carregamento de dados

**Conteúdo:**
- `<?php require includes/tarefas_queries.php; ?>` - Importa funções
- Chama funções de `tarefas_queries.php` para buscar dados
- Renderiza HTML com dados
- Referencia CSS externo: `<link rel="stylesheet" href="assets/css/tarefas.css">`
- Referencia JS externo: `<script src="assets/js/tarefas.js"></script>`

**Não contém:**
- ❌ CSS inline (movido para `assets/css/tarefas.css`)
- ❌ Lógica de banco de dados (movida para `includes/tarefas_queries.php`)
- ❌ JavaScript comportamental (movido para `assets/js/tarefas.js`)

---

### **2. `includes/tarefas_queries.php` (BD)**
**Responsabilidade:** Todas as queries de BD centralizadas

**Funções:**
```php
// Rotinas
buscarRotinasFixas()
criarControleRotinaSeDia()
contarRotinasConcluidas()

// Tarefas
buscarTarefasPendentes()
contarEstatisticas()

// Subtarefas
buscarSubtarefas()
mapearSubtarefasPorTarefa()
```

**Benefícios:**
- ✅ Fácil de testar
- ✅ Reutilizável em outros arquivos
- ✅ Centralizado (manutenção)
- ✅ Sem duplicação de queries

**Uso em `tarefas.php`:**
```php
<?php
require_once 'includes/tarefas_queries.php';

$rotinas = buscarRotinasFixas($pdo, $userId, $dataHoje);
$tarefas = buscarTarefasPendentes($pdo, $userId);
$stats = contarEstatisticas($tarefas);
?>
```

---

### **3. `assets/css/tarefas.css` (ESTILOS)**
**Responsabilidade:** Toda a estilização da página

**Seções:**
- `:root` - Variáveis CSS
- `HEADER` - Estilos do header
- `SECTIONS` - Estrutura de seções
- `ITEMS` - Tarefas e rotinas
- `SUBTASKS` - Subtarefas
- `MODALS` - Modais
- `RESPONSIVE` - Media queries

**Benefícios:**
- ✅ Cache HTTP (arquivo estático)
- ✅ Reutilizável
- ✅ Fácil de atualizar estilos
- ✅ Separação de concerns

**Uso em `tarefas.php`:**
```html
<link rel="stylesheet" href="assets/css/tarefas.css">
```

---

### **4. `assets/js/tarefas.js` (COMPORTAMENTO)**
**Responsabilidade:** Toda a lógica JavaScript

**Estrutura Namespace:**
```javascript
const TarefasApp = {
    modal: { /* métodos de modal */ },
    tarefa: { /* métodos de tarefa */ },
    rotina: { /* métodos de rotina */ },
    subtarefa: { /* métodos de subtarefa */ },
    utils: { /* funções auxiliares */ },
    init() { /* inicialização */ }
}
```

**Benefícios:**
- ✅ Sem conflitos de namespace globais
- ✅ Bem organizado
- ✅ Fácil de estender
- ✅ Cache HTTP
- ✅ Reutilizável

**Uso em `tarefas.php`:**
```html
<!-- No HTML: -->
<button onclick="TarefasApp.modal.abrirTarefa()">Nova Tarefa</button>
<button onclick="TarefasApp.tarefa.completar(<?php echo $task['id']; ?>)">✓</button>

<!-- No fim do body: -->
<script src="assets/js/tarefas.js"></script>
```

---

## 🔄 Fluxo de Dados

```
┌─────────────────────────────────────────────┐
│ tarefas.php (REQUEST)                       │
│ 1. Inclui tarefas_queries.php              │
│ 2. Chama buscarRotinasFixas($pdo, ...)     │
│ 3. Chama buscarTarefasPendentes($pdo, ...) │
│ 4. Chama buscarSubtarefas($pdo, ...)       │
└──────────────────┬──────────────────────────┘
                   │
                   ├─→ includes/tarefas_queries.php (BD)
                   │   └─→ Retorna arrays de dados
                   │
                   ├─→ assets/css/tarefas.css (via <link>)
                   │   └─→ Cache HTTP
                   │
                   ├─→ assets/js/tarefas.js (via <script>)
                   │   └─→ Inicializa TarefasApp
                   │
                   └─→ RENDER HTML
                       └─→ Browser exibe página
```

---

## 🎮 Interações do Usuário

### Exemplo: Adicionar Nova Tarefa

```
1. Usuário clica botão "Nova Tarefa"
   └─→ HTML: onclick="TarefasApp.modal.abrirTarefa()"
   └─→ JS: TarefasApp.modal.abrirTarefa() - abre modal

2. Usuário preenche form e clica "Salvar"
   └─→ HTML: <form id="formNovaTarefa">
   └─→ JS: formNovaTarefa.addEventListener('submit', ...)
   └─→ JS: TarefasApp.tarefa.adicionarNova(event)

3. AJAX POST para adicionar_tarefa.php
   └─→ Backend: Valida e insere no BD
   └─→ Response: JSON { success: true }

4. JS trata resposta e reload
   └─→ JS: location.reload()
   └─→ Browser: Request novo GET para tarefas.php

5. Servidor retorna HTML atualizado
   └─→ PHP: Chama tarefas_queries.php novamente
   └─→ PHP: Renderiza com nova tarefa
   └─→ Browser: Exibe página atualizada
```

---

## 📊 Vantagens da Arquitetura

### ✅ Separação de Concerns
- **tarefas.php:** Apenas HTML + dados
- **tarefas_queries.php:** Apenas lógica de BD
- **tarefas.css:** Apenas estilos
- **tarefas.js:** Apenas comportamento

### ✅ Reusabilidade
- Funções em `tarefas_queries.php` podem ser usadas em outros arquivos
- CSS pode ser estendido com novas classes
- JavaScript é modularizado com namespace

### ✅ Manutenibilidade
- Fácil encontrar onde cada coisa está
- Mudanças isoladas (CSS não afeta PHP)
- Testes simplificados

### ✅ Performance
- CSS e JS em cache HTTP
- Sem repetição de código
- Queries otimizadas centralizadas

### ✅ Escalabilidade
- Adicionar novos modais? Só edit `tarefas.js`
- Mudar cores? Só edit `tarefas.css`
- Alterar query? Só edit `tarefas_queries.php`

---

## 🚀 Como Adicionar Novas Funcionalidades

### Exemplo: Adicionar um novo método de tarefa

**1. Se for BD, add em `tarefas_queries.php`:**
```php
function buscarTarefasArquivadas(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM tarefas 
        WHERE id_usuario = ? AND status = 'arquivada'
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**2. Se for HTML, add em `tarefas.php`:**
```php
<?php $arquivadas = buscarTarefasArquivadas($pdo, $userId); ?>
<div id="secaoArquivadas">...</div>
```

**3. Se for CSS, add em `tarefas.css`:**
```css
.item.arquivada {
    opacity: 0.5;
    background: rgba(100, 100, 100, 0.1);
}
```

**4. Se for JS, add em `tarefas.js`:**
```javascript
TarefasApp.tarefa.arquivar = async function(id) {
    // lógica
}
```

---

## 📝 Checklist de Desenvolvimento

- [ ] Nova funcionalidade precisa de queries? → Edit `tarefas_queries.php`
- [ ] Precisa de HTML? → Edit `tarefas.php`
- [ ] Precisa de estilos? → Edit `tarefas.css`
- [ ] Precisa de interação? → Edit `tarefas.js`
- [ ] Testes de segurança? → Verificar `includes/tarefas_queries.php`
- [ ] Cache atualizado? → Limpar cache do navegador

---

**Última Atualização:** 17 de Outubro de 2025
**Status:** ✅ Arquitetura Pronta para Produção
