// theme-customizer.js - Sistema de Temas Personalizados Avançado

class ThemeCustomizer {
    constructor() {
        this.currentTheme = localStorage.getItem('customTheme') || 'default';
        this.themes = this.initializeThemes();
        this.init();
    }

    initializeThemes() {
        return {
            default: {
                name: 'Padrão',
                colors: {
                    primary: '#e50914',
                    secondary: '#ff4d55',
                    background: '#0d0d0f',
                    surface: '#141417',
                    text: '#f5f5f1',
                    textSecondary: '#c7c7cb',
                    border: 'rgba(255,255,255,0.08)',
                    success: '#28a745',
                    warning: '#ffc107',
                    danger: '#dc3545',
                    info: '#17a2b8'
                }
            },
            ocean: {
                name: 'Oceano',
                colors: {
                    primary: '#0066cc',
                    secondary: '#3399ff',
                    background: '#0a0e1a',
                    surface: '#1a2332',
                    text: '#e6f3ff',
                    textSecondary: '#b3d9ff',
                    border: 'rgba(0,102,204,0.2)',
                    success: '#00cc66',
                    warning: '#ffaa00',
                    danger: '#ff3366',
                    info: '#00aaff'
                }
            },
            forest: {
                name: 'Floresta',
                colors: {
                    primary: '#2d5016',
                    secondary: '#4a7c59',
                    background: '#0f1b0f',
                    surface: '#1a2e1a',
                    text: '#e8f5e8',
                    textSecondary: '#c4e6c4',
                    border: 'rgba(45,80,22,0.3)',
                    success: '#28a745',
                    warning: '#ffc107',
                    danger: '#dc3545',
                    info: '#17a2b8'
                }
            },
            sunset: {
                name: 'Pôr do Sol',
                colors: {
                    primary: '#ff6b35',
                    secondary: '#ff8e53',
                    background: '#1a0f0a',
                    surface: '#2e1a0f',
                    text: '#fff5f0',
                    textSecondary: '#ffd6c4',
                    border: 'rgba(255,107,53,0.2)',
                    success: '#28a745',
                    warning: '#ffc107',
                    danger: '#dc3545',
                    info: '#17a2b8'
                }
            },
            purple: {
                name: 'Roxo',
                colors: {
                    primary: '#6f42c1',
                    secondary: '#8e44ad',
                    background: '#1a0f1a',
                    surface: '#2e1a2e',
                    text: '#f0e6f0',
                    textSecondary: '#d6b3d6',
                    border: 'rgba(111,66,193,0.2)',
                    success: '#28a745',
                    warning: '#ffc107',
                    danger: '#dc3545',
                    info: '#17a2b8'
                }
            },
            minimal: {
                name: 'Minimalista',
                colors: {
                    primary: '#333333',
                    secondary: '#666666',
                    background: '#ffffff',
                    surface: '#f8f9fa',
                    text: '#212529',
                    textSecondary: '#6c757d',
                    border: 'rgba(0,0,0,0.1)',
                    success: '#28a745',
                    warning: '#ffc107',
                    danger: '#dc3545',
                    info: '#17a2b8'
                }
            }
        };
    }

    init() {
        this.createThemeSelector();
        this.createCustomThemeBuilder();
        this.applyTheme(this.currentTheme);
        this.setupEventListeners();
    }

    createThemeSelector() {
        const themeSelector = document.createElement('div');
        themeSelector.className = 'theme-selector';
        themeSelector.innerHTML = `
            <div class="theme-selector-header">
                <h6><i class="bi bi-palette me-2"></i>Temas</h6>
                <button class="btn btn-sm btn-outline-light" id="customizeTheme">
                    <i class="bi bi-gear"></i>
                </button>
            </div>
            <div class="theme-options">
                ${Object.entries(this.themes).map(([key, theme]) => `
                    <div class="theme-option ${key === this.currentTheme ? 'active' : ''}" data-theme="${key}">
                        <div class="theme-preview">
                            <div class="color-swatch" style="background: ${theme.colors.primary}"></div>
                            <div class="color-swatch" style="background: ${theme.colors.secondary}"></div>
                            <div class="color-swatch" style="background: ${theme.colors.surface}"></div>
                        </div>
                        <span class="theme-name">${theme.name}</span>
                    </div>
                `).join('')}
            </div>
        `;

        // Adicionar ao sidebar
        const sidebar = document.querySelector('.sidebar-nav .offcanvas-body');
        if (sidebar) {
            sidebar.appendChild(themeSelector);
        }
    }

    createCustomThemeBuilder() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'themeBuilderModal';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-palette me-2"></i>Construtor de Temas
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Configurações de Cores</h6>
                                <div class="theme-builder-form">
                                    ${Object.entries(this.themes.default.colors).map(([key, value]) => `
                                        <div class="form-group mb-3">
                                            <label class="form-label">${this.getColorLabel(key)}</label>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color" 
                                                       id="color_${key}" value="${value}">
                                                <input type="text" class="form-control" 
                                                       id="hex_${key}" value="${value}">
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Preview do Tema</h6>
                                <div class="theme-preview-container">
                                    <div class="preview-card">
                                        <div class="preview-header">Dashboard</div>
                                        <div class="preview-content">
                                            <div class="preview-metric">
                                                <div class="preview-value">R$ 1.234,56</div>
                                                <div class="preview-label">Receitas</div>
                                            </div>
                                            <div class="preview-button">Botão</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Salvar Tema</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="themeName" 
                                           placeholder="Nome do tema personalizado">
                                    <button class="btn btn-danger" id="saveCustomTheme">
                                        <i class="bi bi-save me-2"></i>Salvar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    }

    getColorLabel(key) {
        const labels = {
            primary: 'Cor Primária',
            secondary: 'Cor Secundária',
            background: 'Fundo Principal',
            surface: 'Fundo de Superfície',
            text: 'Texto Principal',
            textSecondary: 'Texto Secundário',
            border: 'Bordas',
            success: 'Sucesso',
            warning: 'Aviso',
            danger: 'Perigo',
            info: 'Informação'
        };
        return labels[key] || key;
    }

    setupEventListeners() {
        // Seleção de tema
        document.addEventListener('click', (e) => {
            if (e.target.closest('.theme-option')) {
                const themeKey = e.target.closest('.theme-option').dataset.theme;
                this.selectTheme(themeKey);
            }
        });

        // Abrir construtor de temas
        document.addEventListener('click', (e) => {
            if (e.target.id === 'customizeTheme') {
                this.openThemeBuilder();
            }
        });

        // Salvar tema personalizado
        document.addEventListener('click', (e) => {
            if (e.target.id === 'saveCustomTheme') {
                this.saveCustomTheme();
            }
        });

        // Atualizar preview em tempo real
        document.addEventListener('input', (e) => {
            if (e.target.matches('.theme-builder-form input[type="color"], .theme-builder-form input[type="text"]')) {
                this.updatePreview();
            }
        });
    }

    selectTheme(themeKey) {
        this.currentTheme = themeKey;
        this.applyTheme(themeKey);
        
        // Atualizar UI
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.remove('active');
        });
        document.querySelector(`[data-theme="${themeKey}"]`).classList.add('active');
        
        localStorage.setItem('customTheme', themeKey);
    }

    applyTheme(themeKey) {
        const theme = this.themes[themeKey];
        if (!theme) return;

        const root = document.documentElement;
        Object.entries(theme.colors).forEach(([key, value]) => {
            root.style.setProperty(`--${key}`, value);
        });

        // Aplicar classes específicas do tema
        document.body.className = document.body.className.replace(/theme-\w+/g, '');
        document.body.classList.add(`theme-${themeKey}`);
    }

    openThemeBuilder() {
        const modal = new bootstrap.Modal(document.getElementById('themeBuilderModal'));
        modal.show();
        
        // Carregar cores atuais
        this.loadCurrentColors();
    }

    loadCurrentColors() {
        const currentTheme = this.themes[this.currentTheme];
        Object.entries(currentTheme.colors).forEach(([key, value]) => {
            const colorInput = document.getElementById(`color_${key}`);
            const hexInput = document.getElementById(`hex_${key}`);
            if (colorInput && hexInput) {
                colorInput.value = value;
                hexInput.value = value;
            }
        });
    }

    updatePreview() {
        const colors = {};
        Object.keys(this.themes.default.colors).forEach(key => {
            const colorInput = document.getElementById(`color_${key}`);
            if (colorInput) {
                colors[key] = colorInput.value;
            }
        });

        // Aplicar cores ao preview
        const preview = document.querySelector('.theme-preview-container');
        if (preview) {
            preview.style.setProperty('--preview-primary', colors.primary);
            preview.style.setProperty('--preview-secondary', colors.secondary);
            preview.style.setProperty('--preview-background', colors.background);
            preview.style.setProperty('--preview-surface', colors.surface);
            preview.style.setProperty('--preview-text', colors.text);
            preview.style.setProperty('--preview-text-secondary', colors.textSecondary);
        }
    }

    saveCustomTheme() {
        const themeName = document.getElementById('themeName').value;
        if (!themeName.trim()) {
            alert('Por favor, digite um nome para o tema');
            return;
        }

        const colors = {};
        Object.keys(this.themes.default.colors).forEach(key => {
            const colorInput = document.getElementById(`color_${key}`);
            if (colorInput) {
                colors[key] = colorInput.value;
            }
        });

        const customTheme = {
            name: themeName,
            colors: colors
        };

        // Salvar tema personalizado
        const customThemes = JSON.parse(localStorage.getItem('customThemes') || '{}');
        const themeKey = 'custom_' + Date.now();
        customThemes[themeKey] = customTheme;
        localStorage.setItem('customThemes', JSON.stringify(customThemes));

        // Adicionar ao seletor
        this.themes[themeKey] = customTheme;
        this.refreshThemeSelector();

        alert('Tema personalizado salvo com sucesso!');
        bootstrap.Modal.getInstance(document.getElementById('themeBuilderModal')).hide();
    }

    refreshThemeSelector() {
        const themeOptions = document.querySelector('.theme-options');
        if (themeOptions) {
            themeOptions.innerHTML = Object.entries(this.themes).map(([key, theme]) => `
                <div class="theme-option ${key === this.currentTheme ? 'active' : ''}" data-theme="${key}">
                    <div class="theme-preview">
                        <div class="color-swatch" style="background: ${theme.colors.primary}"></div>
                        <div class="color-swatch" style="background: ${theme.colors.secondary}"></div>
                        <div class="color-swatch" style="background: ${theme.colors.surface}"></div>
                    </div>
                    <span class="theme-name">${theme.name}</span>
                </div>
            `).join('');
        }
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    new ThemeCustomizer();
});
