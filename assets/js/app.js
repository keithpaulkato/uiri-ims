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

// Dark mode toggle
function setTheme(theme) {
    document.body.dataset.theme = theme;
    localStorage.setItem('uiri-theme', theme);
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
        toggle.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const saved = localStorage.getItem('uiri-theme');
    const theme = saved === 'dark' ? 'dark' : 'light';
    setTheme(theme);

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

    const toggle = document.getElementById('themeToggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            setTheme(document.body.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    }

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

function applySidebarWidth(width) {
    const mainWrapper = document.querySelector('.main-wrapper');
    document.documentElement.style.setProperty('--sidebar-w', width);
    if (mainWrapper) {
        mainWrapper.style.marginLeft = width;
    }
}

function persistSidebarWidth(width) {
    localStorage.setItem('uiri-sidebar-width', width);
}

function restoreSidebarWidth() {
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
        mainWrapper.classList.toggle('sidebar-collapsed', isDesktop && !shouldOpen);
        if (shouldOpen) {
            const width = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-w').trim();
            mainWrapper.style.marginLeft = width;
        } else {
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
    if (window.innerWidth > 768) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) {
            sidebar.classList.remove('open');
            sidebar.classList.toggle('closed', !sidebar.classList.contains('open'));
        }
        if (overlay) {
            overlay.classList.remove('show');
        }
    }
});
