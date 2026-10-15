(() => {
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];

    const root = document.documentElement;
    const sidebar = qs('#sidebar');
    const scrim = qs('#sidebarScrim');

    const collapseButton = qs('#sidebarCollapseButton');
    const syncCollapseLabel = () => {
        const collapsed = root.classList.contains('sidebar-collapsed');
        collapseButton?.setAttribute('aria-label', collapsed ? 'Open menu' : 'Collapse menu');
        collapseButton?.setAttribute('title', collapsed ? 'Open menu' : 'Collapse menu');
    };

    const setDesktopCollapsed = (collapsed) => {
        root.classList.toggle('sidebar-collapsed', collapsed);
        localStorage.setItem('netrescue-sidebar', collapsed ? 'collapsed' : 'expanded');
        syncCollapseLabel();
    };

    syncCollapseLabel();

    const clock = qs('#appClock');
    const clockText = clock ? qs('b', clock) : null;
    if (clock && clockText) {
        const tick = () => {
            const now = new Date();
            clock.dateTime = now.toISOString();
            clockText.textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        };
        tick();
        setInterval(tick, 1000);
    }

    const themeButton = qs('#themeToggle');
    const syncThemeLabel = () => {
        const light = root.classList.contains('theme-light');
        themeButton?.setAttribute('aria-label', light ? 'Use dark mode' : 'Use light mode');
        themeButton?.setAttribute('title', light ? 'Dark mode' : 'Light mode');
    };
    syncThemeLabel();
    themeButton?.addEventListener('click', () => {
        const light = !root.classList.contains('theme-light');
        root.classList.toggle('theme-light', light);
        localStorage.setItem('netrescue-theme', light ? 'light' : 'dark');
        syncThemeLabel();
    });

    collapseButton?.addEventListener('click', () => {
        setDesktopCollapsed(!root.classList.contains('sidebar-collapsed'));
    });

    const openMobileSidebar = () => {
        sidebar?.classList.add('is-open');
        scrim?.classList.add('is-open');
        document.body.classList.add('menu-open');
    };

    const closeMobileSidebar = () => {
        sidebar?.classList.remove('is-open');
        scrim?.classList.remove('is-open');
        document.body.classList.remove('menu-open');
    };

    const nav = qs('.main-nav');
    const rail = qs('.nav-rail');
    const navLinks = qsa('.nav-link', nav || document);
    const placeRail = (link) => {
        if (!nav || !rail || !link) return;
        const top = link.offsetTop + (link.offsetHeight - rail.offsetHeight) / 2;
        rail.style.transform = `translateY(${top}px)`;
        rail.style.opacity = '1';
    };
    const activeLink = qs('.nav-link.is-active', nav || document);
    placeRail(activeLink);
    navLinks.forEach((link) => link.addEventListener('mouseenter', () => placeRail(link)));
    nav?.addEventListener('mouseleave', () => placeRail(activeLink));
    window.addEventListener('resize', () => placeRail(qs('.nav-link.is-active', nav || document) || activeLink));

    qs('#mobileMenuButton')?.addEventListener('click', openMobileSidebar);
    scrim?.addEventListener('click', closeMobileSidebar);
    qsa('.main-nav .nav-link').forEach((link) => link.addEventListener('click', closeMobileSidebar));

    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    };

    const closeModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!qsa('.modal-backdrop.is-open').length) document.body.classList.remove('modal-open');
    };

    window.NetRescue = { openModal, closeModal };

    qsa('[data-open-modal]').forEach((button) => button.addEventListener('click', () => openModal(button.dataset.openModal)));
    qsa('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop'))));
    qsa('.modal-backdrop').forEach((backdrop) => backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) closeModal(backdrop);
    }));

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        qsa('.modal-backdrop.is-open').forEach(closeModal);
        closeMobileSidebar();
    });

    const recoveryForm = qs('#recoveryModalForm');
    const recoveryText = qs('#recoveryModalText');
    qsa('[data-recovery-action]').forEach((button) => {
        button.addEventListener('click', () => {
            if (recoveryForm) recoveryForm.action = button.dataset.recoveryAction;
            if (recoveryText) recoveryText.textContent = button.dataset.recoveryText || 'NetRescue will run only the approved repair and verify the result.';
            openModal('recoveryModal');
        });
    });

    const busyOverlay = qs('#busyOverlay');
    const busyTitle = qs('#busyTitle');
    const busyDetail = qs('#busyDetail');
    const busyElapsed = qs('#busyElapsed');
    let operationBusy = false;
    let busyStarted = 0;
    let busyTimer;
    const resetBusy = () => {
        operationBusy = false;
        clearInterval(busyTimer);
        busyOverlay?.classList.remove('is-open');
        busyOverlay?.setAttribute('aria-hidden', 'true');
        qsa('.js-busy-form button[type="submit"]').forEach((button) => button.disabled = false);
    };
    window.addEventListener('pageshow', resetBusy);
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-busy-form')) return;
        if (operationBusy) { event.preventDefault(); return; }
        operationBusy = true;
        busyStarted = Date.now();
        qsa('button[type="submit"]', form).forEach((button) => button.disabled = true);
        const repairing = new URL(form.action, window.location.href).pathname.endsWith('/recover');
        if (busyTitle) busyTitle.textContent = repairing
            ? 'Checking the condition, repairing, and verifying…' : (form.dataset.busyText || 'Working…');
        if (busyDetail) busyDetail.textContent = repairing
            ? 'Verification and the result update are automatic, even with scheduled checks paused.'
            : 'The page will update when this request finishes.';
        if (busyElapsed) busyElapsed.textContent = 'Elapsed: 0 seconds';
        busyTimer = setInterval(() => {
            if (busyElapsed) busyElapsed.textContent = `Elapsed: ${Math.floor((Date.now() - busyStarted) / 1000)} seconds`;
        }, 1000);
        if (busyOverlay) {
            busyOverlay.classList.add('is-open');
            busyOverlay.setAttribute('aria-hidden', 'false');
        }
    });

    qsa('[data-dismiss-toast]').forEach((button) => button.addEventListener('click', () => button.closest('[data-toast]')?.remove()));
    qsa('[data-toast]').forEach((toast) => setTimeout(() => toast.remove(), 6500));

    const initialCheckId = Number(document.body.dataset.latestCheckId || 0);
    if (window.location.pathname === '/') {
        setInterval(async () => {
            // A before/after reading can change /status during repair. Never
            // navigate away from an active repair to refresh those partial results.
            if (document.hidden || operationBusy) return;
            try {
                const response = await fetch('/status', { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const data = await response.json();
                if (!operationBusy && Number(data.latest_check_id || 0) > initialCheckId) window.location.reload();
            } catch (_) {
                // Auto-refresh is a convenience. Monitoring continues even if this browser request fails.
            }
        }, 15000);
    }

    const nextCheck = qs('[data-next-check]');
    if (nextCheck?.dataset.nextCheck) {
        const target = new Date(nextCheck.dataset.nextCheck);
        const updateCountdown = () => {
            const diff = Math.max(0, target.getTime() - Date.now());
            if (diff === 0) {
                nextCheck.textContent = 'Due now';
                return;
            }
            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            nextCheck.textContent = `${minutes}m ${String(seconds).padStart(2, '0')}s`;
        };
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    const recordBody = qs('#recordModalBody');
    const openRecord = async (url) => {
        if (!recordBody) {
            window.location.href = url;
            return;
        }
        recordBody.innerHTML = '<p class="quiet">Opening…</p>';
        openModal('recordModal');
        try {
            const response = await fetch(url, { headers: { 'X-Panel': '1', Accept: 'text/html' }, cache: 'no-store' });
            if (!response.ok) throw new Error('panel');
            recordBody.innerHTML = await response.text();
        } catch (_) {
            window.location.href = url;
        }
    };

    document.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-href]');
        if (!row || event.target.closest('a, button')) return;
        const href = row.dataset.href || '';
        if (href.includes('/incidents/')) {
            openRecord(href);
            return;
        }
        window.location.href = href;
    });

    let chartSerial = 0;
    const renderChart = (chart) => {
        const svg = qs('svg', chart);
        const scale = qs('.plot-scale', chart);
        const dataEl = qs('.chart-data', chart);
        if (!svg || !dataEl) return;
        let data = {};
        try { data = JSON.parse(dataEl.textContent || '{}'); } catch (_) { return; }
        const w = Math.round(svg.clientWidth);
        const h = Math.round(svg.clientHeight);
        if (w < 24 || h < 24) return;
        if (!chart.dataset.glow) chart.dataset.glow = `glow${chartSerial += 1}`;
        svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        const numbers = [];
        (data.series || []).forEach((series) => {
            (series.values || []).forEach((value) => { if (typeof value === 'number') numbers.push(value); });
        });
        const peak = Math.max(1, ...numbers, 0);
        const warning = Number(data.warning || 0);
        const showWarning = warning > 0 && warning <= peak * 1.35;
        const axisMax = Math.max(peak * 1.12, showWarning ? warning * 1.08 : 0);
        const padY = 8;
        const usableH = Math.max(1, h - (padY * 2));
        const count = Math.max(1, Number(data.count || 0));
        const xAt = (index) => (count <= 1 ? w / 2 : (index / (count - 1)) * (w - 4) + 2);
        const yAt = (value) => padY + ((1 - (value / axisMax)) * usableH);
        const format = (value) => (value >= 10 ? String(Math.round(value)) : value.toFixed(1));
        if (scale) {
            scale.replaceChildren();
            [axisMax, axisMax / 2, 0].forEach((value) => {
                const label = document.createElement('span');
                label.textContent = format(value);
                scale.append(label);
            });
        }
        const gid = chart.dataset.glow;
        const parts = [
            `<defs><filter id="${gid}" x="-80%" y="-80%" width="260%" height="260%"><feGaussianBlur stdDeviation="2.2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>`,
            `<line class="plot-grid" x1="0" x2="${w}" y1="${(padY + usableH / 2).toFixed(1)}" y2="${(padY + usableH / 2).toFixed(1)}" />`,
            `<line class="plot-grid" x1="0" x2="${w}" y1="${(h - padY).toFixed(1)}" y2="${(h - padY).toFixed(1)}" />`,
        ];
        if (showWarning) {
            const y = yAt(warning).toFixed(1);
            parts.push(`<line class="plot-warn" x1="0" x2="${w}" y1="${y}" y2="${y}" />`);
        }
        (data.series || []).forEach((series) => {
            const line = [];
            (series.values || []).forEach((value, index) => {
                if (typeof value !== 'number') return;
                line.push(`${xAt(index).toFixed(1)},${yAt(value).toFixed(1)}`);
            });
            if (line.length) parts.push(`<polyline class="trend-line ${series.class || ''}" points="${line.join(' ')}" />`);
        });
        (data.series || []).forEach((series) => {
            (series.values || []).forEach((value, index) => {
                if (typeof value !== 'number') return;
                parts.push(`<circle class="point-dot ${series.class || ''}" cx="${xAt(index).toFixed(1)}" cy="${yAt(value).toFixed(1)}" r="3.2" filter="url(#${gid})" />`);
            });
        });
        const hitW = Math.max(8, (w - 4) / count);
        (data.hits || []).forEach((hit, index) => {
            const payload = JSON.stringify(hit).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            parts.push(`<rect class="chart-point" data-point="${payload}" x="${(xAt(index) - hitW / 2).toFixed(1)}" y="0" width="${hitW.toFixed(1)}" height="${h}" />`);
        });
        svg.innerHTML = parts.join('');
    };

    qsa('.response-chart').forEach((chart) => {
        const tip = qs('.chart-tip', chart);
        const frame = qs('.plot-frame', chart);
        if (frame) {
            const draw = () => {
                const svg = qs('svg', chart);
                if (!svg) return;
                const size = `${Math.round(svg.clientWidth)}x${Math.round(svg.clientHeight)}`;
                if (svg.clientWidth < 24 || size === chart.dataset.drawn) return;
                renderChart(chart);
                chart.dataset.drawn = size;
            };
            draw();
            if ('ResizeObserver' in window) new ResizeObserver(draw).observe(frame);
        }
        if (!tip) return;
        const hide = () => { tip.hidden = true; };
        chart.addEventListener('mousemove', (event) => {
            const point = event.target.closest('[data-point]');
            if (!point) {
                hide();
                return;
            }
            let payload = {};
            try { payload = JSON.parse(point.getAttribute('data-point') || '{}'); } catch (_) { hide(); return; }
            tip.replaceChildren();
            const title = document.createElement('strong');
            title.textContent = `${payload.time || ''} · ${payload.title || ''}`.trim();
            const source = document.createElement('em');
            source.textContent = payload.source || '';
            tip.append(title, source);
            (payload.rows || []).forEach((row) => {
                const line = document.createElement('span');
                const name = document.createElement('b');
                name.textContent = row.name || '';
                const value = document.createElement('i');
                value.textContent = `${row.label || ''} ${row.detail || ''}`.trim();
                value.className = `mark-${row.code || 'none'}`;
                line.append(name, value);
                tip.append(line);
            });
            tip.hidden = false;
            const box = chart.getBoundingClientRect();
            const tipBox = tip.getBoundingClientRect();
            let left = event.clientX - box.left + 14;
            let top = event.clientY - box.top - 12;
            if (left + tipBox.width > box.width - 8) left = event.clientX - box.left - tipBox.width - 14;
            if (top + tipBox.height > box.height - 8) top = Math.max(8, box.height - tipBox.height - 8);
            tip.style.left = `${Math.max(8, left)}px`;
            tip.style.top = `${Math.max(8, top)}px`;
        });
        chart.addEventListener('mouseleave', hide);
    });

    const closeMenus = (except) => {
        qsa('.menu-select.is-open').forEach((menu) => {
            if (menu === except) return;
            menu.classList.remove('is-open');
            qs('.menu-select-trigger', menu)?.setAttribute('aria-expanded', 'false');
        });
    };

    qsa('.filter-row select').forEach((select) => {
        const menu = document.createElement('div');
        menu.className = 'menu-select';
        select.parentNode.insertBefore(menu, select);
        menu.append(select);
        select.classList.add('menu-select-native');
        select.tabIndex = -1;

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'menu-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        if (select.getAttribute('aria-label')) trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
        const label = document.createElement('span');
        const caret = document.createElement('i');
        caret.className = 'menu-select-caret';
        caret.setAttribute('aria-hidden', 'true');
        trigger.append(label, caret);

        const panel = document.createElement('div');
        panel.className = 'menu-select-panel';
        panel.setAttribute('role', 'listbox');

        const sync = () => {
            const current = select.selectedOptions[0];
            label.textContent = current ? current.textContent : '';
            qsa('.menu-select-option', panel).forEach((option) => {
                const on = option.dataset.value === select.value;
                option.classList.toggle('is-selected', on);
                option.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        };

        [...select.options].forEach((option, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'menu-select-option';
            item.setAttribute('role', 'option');
            item.dataset.value = option.value;
            item.style.animationDelay = `${index * 32}ms`;
            item.textContent = option.textContent;
            item.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
                closeMenus();
            });
            panel.append(item);
        });

        trigger.addEventListener('click', () => {
            const open = menu.classList.contains('is-open');
            closeMenus();
            if (!open) {
                menu.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });

        menu.append(trigger, panel);
        sync();
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.menu-select')) closeMenus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenus();
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-copy-value]');
        if (!button) return;
        const value = button.getAttribute('data-copy-value') || '';
        navigator.clipboard?.writeText(value).then(() => {
            const old = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => button.textContent = old, 1200);
        });
    });
})();

