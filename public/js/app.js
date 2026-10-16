(() => {
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];

    const root = document.documentElement;
    const appShell = qs('#appShell');
    const mainPanel = qs('.main-panel');
    const sidebar = qs('#sidebar');
    const scrim = qs('#sidebarScrim');
    const mobileMenuButton = qs('#mobileMenuButton');
    const sidebarCloseButton = qs('#sidebarCloseButton');
    const mobileViewport = window.matchMedia('(max-width: 980px)');
    const focusable = (container) => qsa('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', container)
        .filter((element) => element.tabIndex >= 0 && !element.closest('[hidden], [inert]') && element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
    const trapFocus = (event, container) => {
        const items = focusable(container);
        if (!items.length) { event.preventDefault(); container.focus(); return; }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && (document.activeElement === first || !container.contains(document.activeElement))) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !container.contains(document.activeElement))) {
            event.preventDefault(); first.focus();
        }
    };

    const collapseButton = qs('#sidebarCollapseButton');
    const syncCollapseLabel = () => {
        const collapsed = root.classList.contains('sidebar-collapsed');
        collapseButton?.setAttribute('aria-label', collapsed ? 'Open menu' : 'Collapse menu');
        collapseButton?.setAttribute('title', collapsed ? 'Open menu' : 'Collapse menu');
        collapseButton?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
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
        if (!mobileViewport.matches) return;
        sidebar?.classList.add('is-open');
        if (sidebar) {
            sidebar.inert = false;
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        }
        scrim?.classList.add('is-open');
        mobileMenuButton?.setAttribute('aria-expanded', 'true');
        if (mainPanel) mainPanel.inert = true;
        document.body.classList.add('menu-open');
        sidebarCloseButton?.focus();
    };

    const closeMobileSidebar = (restoreFocus = true) => {
        const wasOpen = sidebar?.classList.contains('is-open');
        sidebar?.classList.remove('is-open');
        if (sidebar) {
            sidebar.inert = mobileViewport.matches;
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
        scrim?.classList.remove('is-open');
        mobileMenuButton?.setAttribute('aria-expanded', 'false');
        if (mainPanel) mainPanel.inert = false;
        document.body.classList.remove('menu-open');
        if (wasOpen && restoreFocus) mobileMenuButton?.focus();
    };
    const syncSidebarViewport = () => {
        closeMobileSidebar(false);
        if (collapseButton) collapseButton.disabled = mobileViewport.matches;
    };
    mobileViewport.addEventListener('change', syncSidebarViewport);
    syncSidebarViewport();

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

    mobileMenuButton?.addEventListener('click', openMobileSidebar);
    sidebarCloseButton?.addEventListener('click', () => closeMobileSidebar());
    scrim?.addEventListener('click', () => closeMobileSidebar());
    qsa('.main-nav .nav-link').forEach((link) => link.addEventListener('click', () => closeMobileSidebar()));

    const modalOpeners = new WeakMap();
    const openModal = (id) => {
        const modal = document.getElementById(id);
        if (!modal) return;
        if (!modal.classList.contains('is-open')) modalOpeners.set(modal, document.activeElement);
        closeMobileSidebar(false);
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        if (appShell) appShell.inert = true;
        document.body.classList.add('modal-open');
        (focusable(modal)[0] || qs('[role="dialog"]', modal))?.focus();
    };

    const closeModal = (modal) => {
        if (!modal) return;
        closeMenus();
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!qsa('.modal-backdrop.is-open').length) {
            document.body.classList.remove('modal-open');
            if (appShell) appShell.inert = false;
            const opener = modalOpeners.get(modal);
            if (opener?.isConnected && !opener.closest('[inert]')) opener.focus();
            else if (mobileViewport.matches) mobileMenuButton?.focus();
        }
    };

    window.NetRescue = { openModal, closeModal };

    qsa('[data-open-modal]').forEach((button) => button.addEventListener('click', () => openModal(button.dataset.openModal)));
    qsa('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop'))));
    qsa('.modal-backdrop').forEach((backdrop) => backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) closeModal(backdrop);
    }));

    document.addEventListener('keydown', (event) => {
        const modal = qsa('.modal-backdrop.is-open').at(-1);
        if (event.key === 'Tab') {
            if (modal) trapFocus(event, modal);
            else if (sidebar?.classList.contains('is-open')) trapFocus(event, sidebar);
            return;
        }
        if (event.key !== 'Escape') return;
        const menu = qs('.menu-select.is-open');
        if (menu) {
            event.preventDefault();
            closeMenus();
            qs('.menu-select-trigger', menu)?.focus();
        } else if (modal) closeModal(modal);
        else closeMobileSidebar();
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
        const incidentLink = event.target.closest('a[data-incident-link]');
        if (incidentLink) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || incidentLink.hasAttribute('download') || (incidentLink.target && incidentLink.target !== '_self')) return;
            event.preventDefault();
            openRecord(incidentLink.href);
            return;
        }
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

    qsa('.table-scroll').forEach((scroll) => {
        const hint = scroll.previousElementSibling;
        if (!hint?.classList.contains('table-scroll-hint')) return;
        const syncHint = () => { hint.hidden = scroll.scrollWidth <= scroll.clientWidth + 1; };
        syncHint();
        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(syncHint);
            observer.observe(scroll);
            const table = qs('table', scroll);
            if (table) observer.observe(table);
        } else window.addEventListener('resize', syncHint);
    });

    const closeMenus = (except) => {
        qsa('.menu-select.is-open').forEach((menu) => {
            if (menu === except) return;
            const trigger = qs('.menu-select-trigger', menu);
            if (menu.contains(document.activeElement)) trigger?.focus();
            menu.classList.remove('is-open');
            trigger?.setAttribute('aria-expanded', 'false');
            const panel = qs('.menu-select-panel', menu);
            if (panel) {
                panel.inert = true;
                panel.setAttribute('aria-hidden', 'true');
            }
        });
    };

    qsa('.filter-row select, select[data-menu-select]').forEach((select, menuIndex) => {
        const menu = document.createElement('div');
        menu.className = 'menu-select';
        select.parentNode.insertBefore(menu, select);
        menu.append(select);
        select.classList.add('menu-select-native');
        select.hidden = true;
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;

        const menuId = select.id || `menu-select-${menuIndex}`;
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = `${menuId}-trigger`;
        trigger.className = 'menu-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', `${menuId}-options`);
        trigger.disabled = select.disabled;
        const label = document.createElement('span');
        label.id = `${menuId}-value`;
        const caret = document.createElement('i');
        caret.className = 'menu-select-caret';
        caret.setAttribute('aria-hidden', 'true');
        trigger.append(label, caret);

        const panel = document.createElement('div');
        panel.id = `${menuId}-options`;
        panel.className = 'menu-select-panel';
        panel.setAttribute('role', 'listbox');
        panel.setAttribute('aria-hidden', 'true');
        panel.inert = true;
        const fieldLabels = [...(select.labels || [])];
        if (fieldLabels.length) {
            fieldLabels.forEach((fieldLabel, labelIndex) => {
                if (!fieldLabel.id) fieldLabel.id = `${menuId}-label-${labelIndex}`;
                fieldLabel.htmlFor = trigger.id;
            });
            const labelledBy = fieldLabels.map((fieldLabel) => fieldLabel.id).join(' ');
            trigger.setAttribute('aria-labelledby', `${labelledBy} ${label.id}`);
            panel.setAttribute('aria-labelledby', labelledBy);
        } else {
            panel.setAttribute('aria-label', select.getAttribute('aria-label') || select.name || 'Options');
        }
        const items = [];
        let activeIndex = select.selectedIndex;
        const focusOption = (index) => {
            if (!items.length) return;
            activeIndex = index;
            items.forEach((item, itemIndex) => item.classList.toggle('is-focused', itemIndex === index));
            items[index]?.focus();
        };
        const nextOption = (start, direction) => {
            for (let offset = 1; offset <= items.length; offset += 1) {
                const index = (start + (offset * direction) + items.length) % items.length;
                if (!items[index].disabled) return index;
            }
            return -1;
        };
        const positionPanel = () => {
            // A fixed panel escapes the modal body's scrolling clip. Keep its
            // edges within the viewport, including short landscape screens.
            if (!select.closest('.modal-card') || !menu.classList.contains('is-open')) return;
            const bounds = trigger.getBoundingClientRect();
            const gap = 6;
            const margin = 12;
            const viewportHeight = window.innerHeight;
            const viewportWidth = document.documentElement.clientWidth;
            const width = Math.min(bounds.width, Math.max(0, viewportWidth - (margin * 2)));
            panel.style.position = 'fixed';
            panel.style.minWidth = `${width}px`;
            panel.style.width = `${width}px`;
            panel.style.left = `${Math.min(Math.max(margin, bounds.left), viewportWidth - width - margin)}px`;
            panel.style.overflowY = 'auto';
            const desiredHeight = Math.min(panel.scrollHeight + panel.offsetHeight - panel.clientHeight, 300);
            const below = viewportHeight - bounds.bottom - gap - margin;
            const above = bounds.top - gap - margin;
            const aboveTrigger = below < desiredHeight && above > below;
            const availableHeight = Math.max(0, aboveTrigger ? above : below);
            const height = Math.min(desiredHeight, availableHeight);
            panel.style.maxHeight = `${height}px`;
            panel.style.top = `${aboveTrigger ? bounds.top - gap - height : bounds.bottom + gap}px`;
        };
        const openMenu = (index = select.selectedIndex) => {
            closeMenus(menu);
            menu.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            panel.inert = false;
            panel.setAttribute('aria-hidden', 'false');
            positionPanel();
            // Resolve the opening styles before moving keyboard focus.
            panel.getBoundingClientRect();
            focusOption(items[index] && !items[index].disabled ? index : nextOption(-1, 1));
        };

        const sync = () => {
            const current = select.selectedOptions[0];
            label.textContent = current ? current.textContent : '';
            if (!fieldLabels.length) trigger.setAttribute('aria-label', `${select.getAttribute('aria-label') || select.name || 'Choose an option'}: ${label.textContent}`);
            items.forEach((option, index) => {
                const on = index === select.selectedIndex;
                option.classList.toggle('is-selected', on);
                option.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        };

        [...select.options].forEach((option, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'menu-select-option';
            item.id = `${menuId}-option-${index}`;
            item.setAttribute('role', 'option');
            item.tabIndex = -1;
            item.disabled = option.disabled;
            item.dataset.value = option.value;
            item.style.animationDelay = `${index * 32}ms`;
            item.textContent = option.textContent;
            item.addEventListener('focus', () => {
                activeIndex = index;
                items.forEach((other, itemIndex) => other.classList.toggle('is-focused', itemIndex === index));
            });
            item.addEventListener('click', () => {
                select.selectedIndex = index;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
                closeMenus();
                trigger.focus();
            });
            items.push(item);
            panel.append(item);
        });

        trigger.addEventListener('click', () => {
            const open = menu.classList.contains('is-open');
            closeMenus();
            if (!open) openMenu();
        });
        trigger.addEventListener('keydown', (event) => {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const index = event.key === 'Home' ? nextOption(-1, 1) : event.key === 'End' ? nextOption(0, -1) : select.selectedIndex;
            openMenu(index);
        });
        panel.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                const index = event.key === 'Home' ? nextOption(-1, 1) : event.key === 'End' ? nextOption(0, -1) : nextOption(activeIndex, event.key === 'ArrowDown' ? 1 : -1);
                focusOption(index);
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                }
                closeMenus();
                trigger.focus();
            }
        });
        select.addEventListener('change', sync);
        select.form?.addEventListener('reset', () => setTimeout(sync, 0));
        if (select.closest('.modal-card')) {
            window.addEventListener('resize', positionPanel);
            document.addEventListener('scroll', positionPanel, true);
        }

        menu.append(trigger, panel);
        sync();
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.menu-select')) closeMenus();
    });
    document.addEventListener('focusin', (event) => {
        if (!event.target.closest('.menu-select')) closeMenus();
    });

    qsa('[data-number-stepper]').forEach((stepper) => {
        const input = qs('input[type="number"]', stepper);
        if (!input) return;
        const buttons = qsa('[data-step-number]', stepper);
        const sync = () => {
            const value = input.valueAsNumber;
            buttons.forEach((button) => {
                const decrease = Number(button.dataset.stepNumber) < 0;
                button.disabled = input.disabled || (Number.isFinite(value) && (decrease ? input.min !== '' && value <= Number(input.min) : input.max !== '' && value >= Number(input.max)));
            });
        };
        buttons.forEach((button) => button.addEventListener('click', () => {
            input.stepUp(Number(button.dataset.stepNumber));
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }));
        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        input.form?.addEventListener('reset', () => setTimeout(sync, 0));
        sync();
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

