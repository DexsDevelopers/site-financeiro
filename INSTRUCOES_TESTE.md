# 🧪 INSTRUÇÕES PARA TESTAR OS BOTÕES

## ✅ MÉTODO 1: Teste Direto (MAIS RÁPIDO)

1. Acesse: `https://gold-quail-250128.hostingersite.com/seu_projeto/tarefas.php`

2. Pressione `F12` para abrir o Console do navegador

3. Cole este comando no console:
```javascript
console.log('=== TESTE DE FUNÇÕES ===');
console.log('mostrarEstatisticas:', typeof window.mostrarEstatisticas);
console.log('toggleRotina:', typeof window.toggleRotina);
console.log('adicionarRotinaFixa:', typeof window.adicionarRotinaFixa);
console.log('editarRotina:', typeof window.editarRotina);
console.log('excluirRotina:', typeof window.excluirRotina);
```

4. **Se todos mostrarem "function"**: ✅ FUNCIONANDO!
   **Se mostrarem "undefined"**: ❌ Cache ainda ativo

---

## ✅ MÉTODO 2: Forçar Recarga SEM Cache

1. Na página tarefas.php, pressione:
   - **Windows/Linux**: `Ctrl + Shift + R`
   - **Mac**: `Cmd + Shift + R`

2. OU no Chrome/Edge:
   - Clique com botão direito no ícone de "Recarregar" (ao lado da URL)
   - Selecione "Esvaziar cache e recarregar de maneira forçada"

3. Depois teste um botão qualquer

---

## ✅ MÉTODO 3: Modo Anônimo

1. Abra janela anônima:
   - **Chrome**: `Ctrl + Shift + N`
   - **Firefox**: `Ctrl + Shift + P`

2. Acesse: `https://gold-quail-250128.hostingersite.com/seu_projeto/tarefas.php`

3. Teste os botões

---

## 🎯 O QUE DEVE ACONTECER:

Quando clicar em qualquer botão:
- ✅ Modal deve abrir
- ✅ Console não deve mostrar erros
- ✅ Botões devem responder

---

## 🔧 SE AINDA NÃO FUNCIONAR:

Execute no console:
```javascript
// Forçar recarga do script
const script = document.createElement('script');
script.src = 'tarefas.php?' + Date.now();
document.body.appendChild(script);
```

Depois teste novamente.

