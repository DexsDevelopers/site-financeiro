// assets/js/tour-lite.js
// TourLite: tour leve, sem dependências, com foco/overlay e popovers
(function () {
    function createEl(tag, className, html) {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (html !== undefined) el.innerHTML = html;
        return el;
    }

    function rectOf(el) {
        const r = el.getBoundingClientRect();
        return { top: r.top + window.scrollY, left: r.left + window.scrollX, width: r.width, height: r.height };
    }

    function clamp(n, min, max) { return Math.max(min, Math.min(max, n)); }

    class TourLite {
        constructor(steps = []) {
            this.steps = steps;
            this.current = 0;
            this.active = false;
            this.overlay = null;
            this.hole = null;
            this.tooltip = null;
            this.onFinish = null;
            this.handleResize = this.position.bind(this);
        }

        start(index = 0) {
            if (!this.steps.length) return;
            this.current = index;
            this.active = true;
            this.build();
            this.showStep();
            window.addEventListener('resize', this.handleResize);
            window.addEventListener('scroll', this.handleResize, { passive: true });
        }

        stop() {
            this.active = false;
            window.removeEventListener('resize', this.handleResize);
            window.removeEventListener('scroll', this.handleResize);
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
            // Overlay
            this.overlay = createEl('div', 'tourlite-overlay');
            this.overlay.style.cssText = `
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.6);
                z-index: 10000;
                pointer-events: auto;
                transition: background 0.2s ease;
            `;
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

            // Tooltip
            this.tooltip = createEl('div', 'tourlite-tooltip');
            this.tooltip.style.cssText = `
                position: absolute;
                max-width: 320px;
                background: var(--card-background, #1e1e1e);
                color: var(--text-primary, #f5f5f1);
                border: 1px solid var(--border-color, rgba(255,255,255,0.1));
                border-radius: 10px;
                padding: 14px 14px 10px 14px;
                z-index: 10001;
                transform-origin: top left;
                transition: transform 0.2s ease, opacity 0.2s ease;
                box-shadow: 0 8px 24px rgba(0,0,0,0.35);
            `;

            const title = createEl('div', 'tourlite-title');
            title.style.cssText = 'font-weight:600;margin-bottom:6px;';
            const desc = createEl('div', 'tourlite-desc');
            desc.style.cssText = 'font-size: 0.95rem; color: var(--text-secondary, #aaa);';
            const actions = createEl('div', 'tourlite-actions');
            actions.style.cssText = 'margin-top:10px; display:flex; gap:8px; justify-content:flex-end;';

            const btnPrev = createEl('button', 'btn btn-sm btn-secondary', '<i class="bi bi-arrow-left"></i> Anterior');
            const btnNext = createEl('button', 'btn btn-sm btn-primary', 'Próximo <i class="bi bi-arrow-right"></i>');
            const btnClose = createEl('button', 'btn btn-sm btn-outline-light', 'Concluir');

            btnPrev.onclick = () => this.prev();
            btnNext.onclick = () => this.next();
            btnClose.onclick = () => this.stop();

            actions.appendChild(btnPrev);
            actions.appendChild(btnNext);
            actions.appendChild(btnClose);
            this.tooltip.appendChild(title);
            this.tooltip.appendChild(desc);
            this.tooltip.appendChild(actions);
            this.overlay.appendChild(this.tooltip);

            this.nodes = { title, desc, btnPrev, btnNext, btnClose };
        }

        showStep(animated = false) {
            const step = this.steps[this.current];
            const el = document.querySelector(step.element);
            if (!el) { this.next(); return; }
            el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            setTimeout(() => this.position(animated), 180);
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

            // Lado preferido
            const side = step.popover?.side || 'bottom';
            const margin = 10;
            let top = r.top, left = r.left;
            if (side === 'bottom') top = r.top + r.height + margin; else
            if (side === 'top') top = r.top - margin - this.tooltip.offsetHeight; else
            if (side === 'right') left = r.left + r.width + margin; else
            if (side === 'left') left = r.left - margin - this.tooltip.offsetWidth;

            // Posição fallback dentro da viewport
            const vw = window.scrollX + document.documentElement.clientWidth;
            const vh = window.scrollY + document.documentElement.clientHeight;
            left = clamp(left, window.scrollX + 8, vw - this.tooltip.offsetWidth - 8);
            top = clamp(top, window.scrollY + 8, vh - this.tooltip.offsetHeight - 8);

            this.tooltip.style.left = `${left}px`;
            this.tooltip.style.top = `${top}px`;
            if (animated) {
                this.tooltip.style.transform = 'scale(0.98)';
                this.tooltip.style.opacity = '0.9';
                requestAnimationFrame(() => {
                    this.tooltip.style.transform = 'scale(1)';
                    this.tooltip.style.opacity = '1';
                });
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
        }
    }

    window.TourLite = TourLite;
})();


