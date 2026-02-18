// assets/js/tour-lite.js
// TourLite: tour leve, sem dependências, com foco/overlay e popovers
(function () {
    function injectStyles() {
        if (document.getElementById('tourlite-styles')) return;
        const style = document.createElement('style');
        style.id = 'tourlite-styles';
        style.textContent = `
            @keyframes tl-fade-in { from { opacity: 0 } to { opacity: 1 } }
            @keyframes tl-scale-in { from { transform: scale(.96); opacity: .85 } to { transform: scale(1); opacity: 1 } }
            .tourlite-overlay { animation: tl-fade-in .2s ease both; }
            .tourlite-tooltip { animation: tl-scale-in .18s ease both; }
            .tourlite-tooltip .tourlite-progress { position:absolute; left:0; top:0; height:3px; background: var(--accent-red, #e50914); border-top-left-radius:10px; border-top-right-radius:10px; transition: width .25s ease; }
            .tourlite-tooltip .tourlite-header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
            .tourlite-tooltip .tourlite-step { font-size:.8rem; color: var(--text-secondary, #aaa); }
            .tourlite-arrow { position:absolute; width:12px; height:12px; background: var(--card-background, #1e1e1e); transform: rotate(45deg); border-left:1px solid var(--border-color, rgba(255,255,255,0.1)); border-top:1px solid var(--border-color, rgba(255,255,255,0.1)); }
            /* Bottom sheet no mobile */
            @media (max-width: 575px) {
                .tourlite-tooltip { 
                    max-width: none !important;
                    width: 100vw !important;
                    left: 0 !important;
                    right: 0 !important;
                    bottom: env(safe-area-inset-bottom);
                    top: auto !important;
                    border-radius: 16px 16px 0 0 !important;
                }
                .tourlite-arrow { display: none !important; }
            }
            /* Reduce motion */
            @media (prefers-reduced-motion: reduce) {
                .tourlite-overlay, .tourlite-tooltip { animation: none !important; }
                .tourlite-tooltip { transition: none !important; }
            }
        `;
        document.head.appendChild(style);
    }
    function createEl(tag, className, html) {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (html !== undefined) el.innerHTML = html;
        return el;
    }

    function rectOf(el) {
        // Coordenadas no espaço da viewport (compatível com overlay fixo)
        const r = el.getBoundingClientRect();
        return { top: r.top, left: r.left, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
    }

    function clamp(n, min, max) { return Math.max(min, Math.min(max, n)); }
    function isInViewportRect(r, pad){
        const vw = document.documentElement.clientWidth;
        const vh = document.documentElement.clientHeight;
        const p = pad || 8;
        return r.top >= p && r.left >= p && r.bottom <= (vh - p) && r.right <= (vw - p);
    }

    class TourLite {
        constructor(steps = []) {
            this.steps = steps;
            this.current = 0;
            this.active = false;
            this.overlay = null;
            this.hole = null;
            this.tooltip = null;
            this.arrow = null;
            this.onFinish = null;
            this.handleResize = this.position.bind(this);
            this.isMobile = (typeof window !== 'undefined') ? (window.innerWidth < 576) : false;
            injectStyles();
        }

        start(index = 0) {
            // Não iniciar se um modal Bootstrap estiver aberto
            if (document.body.classList.contains('modal-open')) return;
            
            if (!this.steps.length) return;
            this.current = index;
            this.active = true;
            
            // Registrar instância global para poder ser parada
            window._activeTourInstance = this;
            
            this.build();
            this.showStep();
            // Throttle com rAF para posicionamento em scroll/resize
            this._onScrollOrResize = () => {
                if (this._raf) return; this._raf = requestAnimationFrame(() => { this._raf = null; this.position(false); });
            };
            window.addEventListener('resize', this._onScrollOrResize);
            window.addEventListener('scroll', this._onScrollOrResize, { passive: true });
        }

        stop() {
            this.active = false;
            
            // Limpar referência global
            if (window._activeTourInstance === this) {
                window._activeTourInstance = null;
            }
            
            window.removeEventListener('resize', this._onScrollOrResize || this.handleResize);
            window.removeEventListener('scroll', this._onScrollOrResize || this.handleResize);
            if (this._kbd) window.removeEventListener('keydown', this._kbd);
            if (this.overlay && this.overlay.parentNode) this.overlay.parentNode.removeChild(this.overlay);
            if (typeof this.onFinish === 'function') this.onFinish();
        }

        next() {
            if (this.current < this.steps.length - 1) {
                this.current++;
                this.showStep(true);
            } else {
                this.stop();
            }
        }

        prev() {
            if (this.current > 0) {
                this.current--;
                this.showStep(true);
            }
        }

        build() {
            // Overlay - z-index menor que modais Bootstrap (1040-1050)
            this.overlay = createEl('div', 'tourlite-overlay');
            this.overlay.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.6);
                z-index: 1030;
                pointer-events: auto;
                transition: background 0.2s ease;
                backdrop-filter: blur(2px);
            `;
            // Clique no overlay avança (sem clicar no buraco/tooltip)
            this.overlay.addEventListener('click', (ev) => {
                if (!this.active) return;
                if (ev.target === this.overlay) this.next();
            });
            document.body.appendChild(this.overlay);

            // Buraco com foco (bordas)
            this.hole = createEl('div', 'tourlite-hole');
            this.hole.style.cssText = `
                position: absolute;
                border-radius: 8px;
                box-shadow: 0 0 0 9999px rgba(0,0,0,0.6), 0 10px 30px rgba(0,0,0,0.35);
                transition: all 0.2s ease;
                pointer-events: none;
            `;
            this.overlay.appendChild(this.hole);

            // Tooltip - z-index menor que modais Bootstrap
            this.tooltip = createEl('div', 'tourlite-tooltip');
            this.tooltip.style.cssText = `
                position: absolute;
                max-width: 320px;
                background: var(--card-background, #1e1e1e);
                color: var(--text-primary, #f5f5f1);
                border: 1px solid var(--border-color, rgba(255,255,255,0.1));
                border-radius: 10px;
                padding: 14px 14px 10px 14px;
                z-index: 1031;
                transform-origin: top left;
                transition: transform 0.2s ease, opacity 0.2s ease;
                box-shadow: 0 8px 24px rgba(0,0,0,0.35);
            `;
            this.tooltip.setAttribute('role', 'dialog');
            this.tooltip.setAttribute('aria-live', 'polite');

            const progress = createEl('div', 'tourlite-progress');
            progress.style.width = '0%';

            const header = createEl('div', 'tourlite-header');
            const title = createEl('div', 'tourlite-title');
            title.style.cssText = 'font-weight:600;margin-bottom:6px;';
            const stepEl = createEl('div', 'tourlite-step');
            stepEl.textContent = '';
            header.appendChild(title);
            header.appendChild(stepEl);
            const desc = createEl('div', 'tourlite-desc');
            desc.style.cssText = 'font-size: 0.95rem; color: var(--text-secondary, #aaa);';
            const actions = createEl('div', 'tourlite-actions');
            actions.style.cssText = 'margin-top:10px; display:flex; gap:8px; justify-content:flex-end;';

            const btnPrev = createEl('button', 'btn btn-sm btn-secondary', '<i class="bi bi-arrow-left"></i> Anterior');
            const btnNext = createEl('button', 'btn btn-sm btn-primary', 'Próximo <i class="bi bi-arrow-right"></i>');
            const btnClose = createEl('button', 'btn btn-sm btn-outline-light', 'Concluir');
            const btnSkip = createEl('button', 'btn btn-sm btn-outline-secondary', 'Pular');

            btnPrev.onclick = () => this.prev();
            btnNext.onclick = () => this.next();
            btnClose.onclick = () => this.stop();
            btnSkip.onclick = () => this.stop();

            actions.appendChild(btnPrev);
            actions.appendChild(btnNext);
            actions.appendChild(btnClose);
            actions.appendChild(btnSkip);
            this.tooltip.appendChild(progress);
            this.tooltip.appendChild(header);
            this.tooltip.appendChild(desc);
            this.tooltip.appendChild(actions);
            this.overlay.appendChild(this.tooltip);
            // Arrow
            this.arrow = createEl('div', 'tourlite-arrow');
            this.tooltip.appendChild(this.arrow);

            this.nodes = { title, stepEl, desc, btnPrev, btnNext, btnClose, progress };

            // Keyboard shortcuts
            this._kbd = (ev) => {
                if (!this.active) return;
                if (ev.key === 'Escape') { this.stop(); }
                if (ev.key === 'ArrowRight') { this.next(); }
                if (ev.key === 'ArrowLeft') { this.prev(); }
            };
            window.addEventListener('keydown', this._kbd);
        }

        showStep(animated = false) {
            const step = this.steps[this.current];
            const el = document.querySelector(step.element);
            if (!el) { this.next(); return; }
            el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            // Aguarda o elemento entrar na viewport antes de posicionar
            const start = performance.now();
            const retry = () => {
                if (!this.active) return;
                const r = el.getBoundingClientRect();
                if (isInViewportRect({ top: r.top, left: r.left, right: r.right, bottom: r.bottom }, 4)) {
                    this.position(animated);
                } else if (performance.now() - start < 1000) {
                    requestAnimationFrame(retry);
                } else {
                    // fallback: posiciona mesmo assim
                    this.position(animated);
                }
            };
            requestAnimationFrame(retry);
        }

        position(animated = false) {
            if (!this.active) return;
            const step = this.steps[this.current];
            const el = document.querySelector(step.element);
            if (!el) return;
            const r = rectOf(el);

            // Atualiza foco/hole
            const pad = 8;
            this.hole.style.left = `${r.left - pad}px`;
            this.hole.style.top = `${r.top - pad}px`;
            this.hole.style.width = `${r.width + pad * 2}px`;
            this.hole.style.height = `${r.height + pad * 2}px`;

            // Atualiza tooltip
            this.nodes.title.textContent = step.popover?.title || '';
            this.nodes.desc.textContent = step.popover?.description || '';

            // Lado preferido + autoposicionamento
            let side = step.popover?.side || 'bottom';
            const margin = 10;
            let top = r.top, left = r.left;
            const vwClient = document.documentElement.clientWidth;
            const vhClient = document.documentElement.clientHeight;
            const ttW = this.tooltip.offsetWidth;
            const ttH = this.tooltip.offsetHeight;

            // No mobile nos comportamos como bottom sheet
            if (this.isMobile) {
                this.tooltip.style.left = `0px`;
                this.tooltip.style.right = `0px`;
                this.tooltip.style.bottom = `0px`;
                this.tooltip.style.top = ``;
                // Centraliza buraco e deixa tooltip fixa em baixo
                // Arrow é oculto via CSS
            } else {
            const fits = {
                bottom: (r.bottom + margin + ttH) <= vhClient,
                top: (r.top - margin - ttH) >= 0,
                right: (r.right + margin + ttW) <= vwClient,
                left: (r.left - margin - ttW) >= 0
            };
            const order = ['bottom','right','top','left'];
            if (!fits[side]) {
                for (let i=0;i<order.length;i++) { if (fits[order[i]]) { side = order[i]; break; } }
            }
            if (side === 'bottom') top = r.top + r.height + margin;
            if (side === 'top') top = r.top - margin - ttH;
            if (side === 'right') left = r.left + r.width + margin;
            if (side === 'left') left = r.left - margin - ttW;

            // Posição fallback dentro da viewport
            const vw = document.documentElement.clientWidth;
            const vh = document.documentElement.clientHeight;
            left = clamp(left, 8, vw - ttW - 8);
            top = clamp(top, 8, vh - ttH - 8);

            this.tooltip.style.left = `${left}px`;
            this.tooltip.style.top = `${top}px`;
            }
            if (animated) {
                this.tooltip.style.transform = 'scale(0.98)';
                this.tooltip.style.opacity = '0.9';
                requestAnimationFrame(() => {
                    this.tooltip.style.transform = 'scale(1)';
                    this.tooltip.style.opacity = '1';
                });
            }

            // Atualiza contador e progresso
            this.nodes.stepEl.textContent = `${this.current + 1}/${this.steps.length}`;
            this.nodes.progress.style.width = `${Math.round(((this.current + 1)/this.steps.length)*100)}%`;

            // Posiciona seta
            const aw = 12;
            if (!this.isMobile && side === 'bottom') {
                this.arrow.style.left = `${clamp(r.left + r.width/2 - left - aw/2, 10, ttW-22)}px`;
                this.arrow.style.top = `-${aw/2}px`;
            } else if (!this.isMobile && side === 'top') {
                this.arrow.style.left = `${clamp(r.left + r.width/2 - left - aw/2, 10, ttW-22)}px`;
                this.arrow.style.top = `${ttH - aw/2}px`;
            } else if (!this.isMobile && side === 'right') {
                this.arrow.style.left = `-${aw/2}px`;
                this.arrow.style.top = `${clamp(r.top + r.height/2 - top - aw/2, 10, ttH-22)}px`;
            } else if (!this.isMobile && side === 'left') {
                this.arrow.style.left = `${ttW - aw/2}px`;
                this.arrow.style.top = `${clamp(r.top + r.height/2 - top - aw/2, 10, ttH-22)}px`;
            }

            // Botões habilitados/labels
            this.nodes.btnPrev.disabled = this.current === 0;
            if (this.current === this.steps.length - 1) {
                this.nodes.btnNext.textContent = 'Concluir';
                this.nodes.btnNext.onclick = () => this.stop();
            } else {
                this.nodes.btnNext.innerHTML = 'Próximo <i class="bi bi-arrow-right"></i>';
                this.nodes.btnNext.onclick = () => this.next();
            }

            // Focus
            this.nodes.btnNext.focus({ preventScroll: true });
        }
    }

    window.TourLite = TourLite;
})();


