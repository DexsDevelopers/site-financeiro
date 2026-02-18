// theme-switcher.js - Sistema de Troca de Temas

document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || 'dark';

    // Aplicar tema salvo
    document.documentElement.setAttribute('data-bs-theme', currentTheme);

    if (themeToggleBtn) {
        // Atualizar ícone do botão
        updateThemeIcon(currentTheme);
        
        themeToggleBtn.addEventListener('click', () => {
            let theme = document.documentElement.getAttribute('data-bs-theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('theme', 'light');
                updateThemeIcon('light');
            } else {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                updateThemeIcon('dark');
            }
        });
    }
});

function updateThemeIcon(theme) {
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        const icon = themeToggleBtn.querySelector('i');
        if (icon) {
            if (theme === 'dark') {
                icon.className = 'bi bi-sun-fill';
            } else {
                icon.className = 'bi bi-moon-fill';
            }
        }
    }
}
