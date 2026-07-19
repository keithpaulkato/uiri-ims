// ============================================================
// UIRI IMS — App JavaScript
// ============================================================

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'flex';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.style.display = 'none';
}

// Close modal on overlay click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
});

// Close dropdowns when clicking outside
document.addEventListener('click', function (e) {
    const userMenu = document.getElementById('userMenu');
    const dropdown = document.getElementById('userDropdown');
    const notifMenu = document.querySelector('.notification-menu');
    const notifDropdown = document.getElementById('notifDropdown');

    if (userMenu && dropdown && !userMenu.contains(e.target)) {
        dropdown.classList.remove('show');
    }

    if (notifMenu && notifDropdown && !notifMenu.contains(e.target)) {
        notifDropdown.classList.remove('show');
    }
});

// Toggle notification panel
document.addEventListener('click', function (e) {
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifBtn && notifDropdown && e.target.closest('#notifBtn')) {
        e.preventDefault();
        notifDropdown.classList.toggle('show');
    }
});

// Theme configuration

document.addEventListener('DOMContentLoaded', function () {
    document.body.dataset.theme = 'light';

    const savedScheme = localStorage.getItem('uiri-dashboard-theme') || 'corporate';
    document.body.dataset.dashboardTheme = savedScheme;
    document.querySelectorAll('.theme-chip').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.themeScheme === savedScheme);
    });

    document.querySelectorAll('.theme-chip').forEach(btn => {
        btn.addEventListener('click', function () {
            const scheme = this.dataset.themeScheme;
            document.body.dataset.dashboardTheme = scheme;
            localStorage.setItem('uiri-dashboard-theme', scheme);
            document.querySelectorAll('.theme-chip').forEach(chip => chip.classList.toggle('active', chip === this));
        });
    });

    const flash = document.getElementById('flashMsg');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity .4s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 5000);
    }

    const statCards = document.querySelectorAll('.stat-card[data-href]');
    statCards.forEach(card => {
        card.addEventListener('click', function () {
            window.location.href = this.dataset.href;
        });
    });

    const fabToggle = document.querySelector('.fab-toggle');
    const fab = document.querySelector('.fab');
    if (fabToggle && fab) {
        fabToggle.addEventListener('click', function () {
            fab.classList.toggle('open');
        });
    }

    const searchInput = document.getElementById('dashboardSearch');
    const searchResults = document.getElementById('searchResults');
    if (searchInput && searchResults) {
        const items = Array.from(document.querySelectorAll('.quick-link-list a')).map(link => ({
            label: link.textContent.trim(),
            href: link.getAttribute('href')
        }));
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            const matches = items.filter(item => item.label.toLowerCase().includes(q));
            searchResults.innerHTML = matches.length ? matches.map(item => `<a href="${item.href}">${item.label}</a>`).join('') : '<span class="dashboard-pill">No matches yet</span>';
        });
    }

    const recentList = document.getElementById('recentlyViewedList');
    if (recentList) {
        const stored = JSON.parse(localStorage.getItem('uiri-recent-items') || '[]');
        if (stored.length) {
            recentList.innerHTML = stored.map(item => `<a href="${item.href}" class="quick-link-list-link">${item.label}</a>`).join('');
        }
    }

    document.querySelectorAll('.js-track-view').forEach(link => {
        link.addEventListener('click', function () {
            const label = this.dataset.label || this.textContent.trim();
            const href = this.getAttribute('href');
            const stored = JSON.parse(localStorage.getItem('uiri-recent-items') || '[]');
            const updated = [{ label, href }, ...stored.filter(item => item.href !== href)].slice(0, 5);
            localStorage.setItem('uiri-recent-items', JSON.stringify(updated));
        });
    });

    initializeSessionIdleGuard();
    initializeDeleteConfirmations();
    initializeUgandanPhoneInputs();

    const valueTarget = document.getElementById('inventoryValueCounter');
    if (valueTarget) {
        const target = parseFloat(valueTarget.dataset.value || '0');
        const format = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'UGX', maximumFractionDigits: 0 });
        let current = 0;
        const step = Math.max(1, Math.round(target / 60));
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            valueTarget.textContent = format.format(current);
        }, 20);
    }

    const fullscreenBtn = document.getElementById('fullscreenDashboard');
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function () {
            try {
                if (!document.fullscreenElement) {
                    // Request fullscreen on the documentElement (entire page)
                    const req = document.documentElement.requestFullscreen?.();
                    if (req && req.catch) req.catch(() => {});
                } else {
                    const exit = document.exitFullscreen?.();
                    if (exit && exit.catch) exit.catch(() => {});
                }
            } catch (err) {
                // Fallback: toggle a CSS-only fullscreen class
                document.body.classList.toggle('is-fullscreen');
            }
        });

        // Reflect state changes from the browser's fullscreen API
        document.addEventListener('fullscreenchange', function () {
            const isFs = !!document.fullscreenElement;
            document.body.classList.toggle('is-fullscreen', isFs);
            fullscreenBtn.textContent = isFs ? '⤫ Exit Full Screen' : '⤢ Full Screen';

            // When exiting fullscreen, ensure the sidebar is visible again on desktop
            if (!isFs) {
                try {
                    // Re-apply stored sidebar width and open it
                    restoreSidebarWidth();
                    toggleSidebar(true);
                } catch (err) {
                    // ignore if functions not available for some pages
                }
            }
        });
    }

    if (fab) {
        document.addEventListener('click', function (e) {
            if (!fab.contains(e.target) && !e.target.closest('.fab-toggle')) {
                fab.classList.remove('open');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                fab.classList.remove('open');
            }
        });
    }

    if (window.AOS) {
        AOS.init({
            once: true,
            duration: 700,
            offset: 120,
        });
    }

    restoreSidebarWidth();
    initializeSidebarResizer();
});

function initializeSessionIdleGuard() {
    const config = window.UIRI_SESSION || {};
    const idleTimeoutMs = Number(config.idleTimeoutMs || 0);
    if (!idleTimeoutMs || !config.timeoutUrl) return;

    let idleTimer = null;
    let lastActivityHandledAt = 0;
    let lastKeepaliveAt = 0;
    let keepaliveInFlight = false;
    const keepaliveIntervalMs = Math.min(300000, Math.max(60000, Math.floor(idleTimeoutMs / 3)));

    const expireSession = () => {
        window.location.replace(config.timeoutUrl);
    };

    const refreshServerSession = () => {
        if (!config.keepaliveUrl || keepaliveInFlight) return;

        const now = Date.now();
        if (now - lastKeepaliveAt < keepaliveIntervalMs) return;

        lastKeepaliveAt = now;
        keepaliveInFlight = true;

        fetch(config.keepaliveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            keepalive: true,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(response => {
            if (response.status === 401) {
                window.location.replace(config.expiredUrl || config.timeoutUrl);
            }
        }).catch(() => {
            // The local idle timer still protects the page if a background ping fails.
        }).finally(() => {
            keepaliveInFlight = false;
        });
    };

    const resetIdleTimer = () => {
        window.clearTimeout(idleTimer);
        idleTimer = window.setTimeout(expireSession, idleTimeoutMs);
        refreshServerSession();
    };

    const handleActivity = () => {
        const now = Date.now();
        if (now - lastActivityHandledAt < 1000) return;
        lastActivityHandledAt = now;
        resetIdleTimer();
    };

    ['click', 'keydown', 'input', 'pointerdown', 'pointermove', 'scroll', 'touchstart'].forEach(eventName => {
        document.addEventListener(eventName, handleActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) handleActivity();
    });

    window.addEventListener('pageshow', resetIdleTimer);
    resetIdleTimer();
}

function initializeDeleteConfirmations() {
    const confirmElements = document.querySelectorAll('.js-delete-confirm');
    if (!confirmElements.length) return;

    const buildMessage = (element) => {
        const title = element.dataset.deleteTitle || 'Confirm deletion';
        const text = element.dataset.deleteText || 'This record will be deleted. Do you want to continue?';
        const confirmText = element.dataset.deleteConfirm || 'Yes, delete';
        return { title, text, confirmText };
    };

    const askToDelete = (element, proceed) => {
        const message = buildMessage(element);

        if (!window.Swal) {
            if (window.confirm(message.title + '\n\n' + message.text)) proceed();
            return;
        }

        window.Swal.fire({
            title: message.title,
            text: message.text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: message.confirmText,
            cancelButtonText: 'No, keep it',
            reverseButtons: true,
            focusCancel: true,
            buttonsStyling: false,
            customClass: {
                popup: 'uiri-delete-alert',
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-outline'
            }
        }).then(result => {
            if (result.isConfirmed) proceed();
        });
    };

    confirmElements.forEach(element => {
        if (element.tagName === 'FORM') {
            element.addEventListener('submit', function (event) {
                event.preventDefault();
                askToDelete(element, () => HTMLFormElement.prototype.submit.call(element));
            });
            return;
        }

        element.addEventListener('click', function (event) {
            event.preventDefault();
            const href = element.getAttribute('href');
            if (!href) return;
            askToDelete(element, () => {
                window.location.href = href;
            });
        });
    });
}

function normalizeUgandanPhoneValue(value, keepPrefix) {
    let digits = String(value || '').replace(/\D+/g, '');
    if (digits.startsWith('256')) {
        digits = digits.slice(3);
    }
    if (digits.startsWith('0')) {
        digits = digits.slice(1);
    }
    digits = digits.slice(0, 9);
    if (!digits && !keepPrefix) return '';
    return '+256 ' + digits;
}

function initializeUgandanPhoneInputs() {
    document.querySelectorAll('.js-ug-phone').forEach(input => {
        const applyValue = keepPrefix => {
            input.value = normalizeUgandanPhoneValue(input.value, keepPrefix);
        };

        if (input.value) {
            applyValue(false);
        }

        input.addEventListener('focus', function () {
            applyValue(true);
        });

        input.addEventListener('input', function () {
            applyValue(true);
        });

        input.addEventListener('blur', function () {
            const digits = input.value.replace(/\D+/g, '').replace(/^256/, '');
            input.value = digits.length === 9 ? '+256 ' + digits : '';
        });

        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                const digits = input.value.replace(/\D+/g, '').replace(/^256/, '');
                input.value = digits.length === 9 ? '+256 ' + digits : '';
            });
        }
    });
}

function applySidebarWidth(width) {
    const mainWrapper = document.querySelector('.main-wrapper');
    document.documentElement.style.setProperty('--sidebar-w', width);
    const numericWidth = parseInt(width, 10) || 250;
    const maxWidth = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-max-w'), 10) || 360;
    const minWidth = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-min-w'), 10) || 220;
    const progress = Math.min(1, Math.max(0, (numericWidth - minWidth) / Math.max(1, maxWidth - minWidth)));
    document.documentElement.style.setProperty('--sidebar-scale', (1 - (progress * 0.12)).toFixed(3));
    if (mainWrapper) {
        mainWrapper.style.marginLeft = width;
    }
}

function persistSidebarWidth(width) {
    localStorage.setItem('uiri-sidebar-width', width);
}

function restoreSidebarWidth() {
    // Don't apply stored sidebar width on mobile — sidebar overlays there
    if (window.innerWidth <= 768) return;
    const stored = localStorage.getItem('uiri-sidebar-width');
    if (stored) {
        applySidebarWidth(stored);
    }
}

function initializeSidebarResizer() {
    const sidebar = document.getElementById('sidebar');
    const resizer = document.getElementById('sidebarResizer');
    if (!sidebar || !resizer) return;

    let startX = 0;
    let startWidth = 0;

    const minWidth = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-min-w'), 10);
    const maxWidth = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-max-w'), 10);

    function onPointerMove(e) {
        const delta = e.clientX - startX;
        let newWidth = startWidth + delta;
        newWidth = Math.min(Math.max(newWidth, minWidth), maxWidth);
        applySidebarWidth(`${newWidth}px`);
    }

    function onPointerUp() {
        document.body.style.cursor = '';
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        persistSidebarWidth(getComputedStyle(document.documentElement).getPropertyValue('--sidebar-w').trim());
    }

    resizer.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' || e.pointerType === 'touch' || e.pointerType === 'pen') {
            e.preventDefault();
            startX = e.clientX;
            startWidth = parseInt(getComputedStyle(sidebar).width, 10);
            document.body.style.cursor = 'ew-resize';
            document.addEventListener('pointermove', onPointerMove);
            document.addEventListener('pointerup', onPointerUp);
        }
    });
}

function toggleSidebar(force) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mainWrapper = document.querySelector('.main-wrapper');
    const menuToggle = document.getElementById('menuToggle');
    if (!sidebar) return;

    const isDesktop = window.innerWidth > 768;
    const shouldOpen = typeof force === 'boolean' ? force : !sidebar.classList.contains('open');

    sidebar.classList.toggle('open', shouldOpen);
    sidebar.classList.toggle('closed', !shouldOpen);

    if (mainWrapper) {
        if (isDesktop) {
            mainWrapper.classList.toggle('sidebar-collapsed', !shouldOpen);
            if (shouldOpen) {
                const width = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-w').trim();
                mainWrapper.style.marginLeft = width;
            } else {
                mainWrapper.style.marginLeft = '0';
            }
        } else {
            // Mobile: sidebar overlays, don't push content
            mainWrapper.classList.remove('sidebar-collapsed');
            mainWrapper.style.marginLeft = '0';
        }
    }

    if (overlay) {
        overlay.classList.toggle('show', shouldOpen && !isDesktop);
    }

    if (menuToggle) {
        menuToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        menuToggle.title = shouldOpen ? 'Close menu' : 'Open menu';
    }
}

// Close sidebar on mobile after clicking a nav link
document.addEventListener('click', function (e) {
    const link = e.target.closest('.sidebar-nav a');
    if (link && window.innerWidth <= 768) {
        toggleSidebar(false);
    }
});

window.addEventListener('resize', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mainWrapper = document.querySelector('.main-wrapper');

    if (!sidebar) return;

    if (window.innerWidth > 768) {
        // Desktop: sidebar should be visible, overlay hidden
        sidebar.classList.remove('open', 'closed');
        if (overlay) overlay.classList.remove('show');
        if (mainWrapper) {
            mainWrapper.classList.remove('sidebar-collapsed');
            const width = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-w').trim();
            mainWrapper.style.marginLeft = width;
        }
    } else {
        // Mobile: sidebar should be hidden off-screen, overlay hidden
        if (!sidebar.classList.contains('open')) {
            sidebar.classList.add('closed');
        }
        if (overlay && !sidebar.classList.contains('open')) {
            overlay.classList.remove('show');
        }
        if (mainWrapper) {
            mainWrapper.classList.remove('sidebar-collapsed');
            mainWrapper.style.marginLeft = '0';
        }
    }
});
