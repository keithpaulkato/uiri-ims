<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'UIRI IMS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- UIRI styles -->
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <!-- SIDEBAR -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo">
                    <img src="{{ asset('img/uiri-logo.webp') }}" alt="UIRI Logo">
                </div>
                <div class="brand-text">
                    <span class="brand-name">UIRI IMS</span>
                    <span class="brand-sub">Inventory System</span>
                </div>
                <button class="sidebar-close" type="button" onclick="toggleSidebar(false)" aria-label="Close sidebar">
                    <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <!-- Branch Switcher -->
            @if(auth()->user()->role === 'Administrator')
            <div class="branch-switcher">
                <label>Active Branch</label>
                <form method="POST" action="{{ route('branch.switch') }}">
                    @csrf
                    <select name="branch_id" onchange="this.form.submit()">
                        @foreach(\App\Models\Branch::all() as $branch)
                            <option value="{{ $branch->id }}" @selected($branch->id == session('active_branch_id', auth()->user()->branch_id))>
                                {{ $branch->name }} @if($branch->is_headquarters) (HQ) @endif
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
            @else
            <div class="branch-badge">
                <span class="branch-dot"></span>
                {{ auth()->user()->branch?->name }}
                @if(auth()->user()->branch?->is_headquarters)<em>(HQ)</em>@endif
            </div>
            @endif

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-section">Main</li>
                    <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <a href="{{ route('dashboard') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-section">Inventory</li>
                    <li class="{{ request()->routeIs('items.*') ? 'active' : '' }}">
                        <a href="{{ route('items.index') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></span>
                            Inventory Items
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <a href="{{ route('categories.index') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                            Categories
                        </a>
                    </li>

                    <li class="nav-section">Stock</li>
                    <li class="{{ request()->routeIs('stock.in') ? 'active' : '' }}">
                        <a href="{{ route('stock.in') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg></span>
                            Stock In
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('stock.out') ? 'active' : '' }}">
                        <a href="{{ route('stock.out') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg></span>
                            Stock Out
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('stock.adjust') ? 'active' : '' }}">
                        <a href="{{ route('stock.adjust') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 6v12m-6-6h12"/></svg></span>
                            Stock Adjustment
                        </a>
                    </li>
                    <li class="{{ request()->routeIs('transactions.index') ? 'active' : '' }}">
                        <a href="{{ route('transactions.index') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></span>
                            Transactions
                        </a>
                    </li>

                    <li class="nav-section">Management</li>
                    <li class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                        <a href="{{ route('suppliers.index') }}">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg></span>
                            Suppliers
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/></svg></span>
                            Requests
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/></svg></span>
                            Procurement
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13l2 2 4-4"/></svg></span>
                            Maintenance
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></span>
                            Transfers
                        </a>
                    </li>
                    @if(auth()->user()->role === 'Administrator')
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                            Users
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/></svg></span>
                            Sections
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/></svg></span>
                            Departments
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06A1.65 1.65 0 0015 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 009 15a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 8.6a1.65 1.65 0 00.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 0015 4.6a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06A1.65 1.65 0 0019.4 15z"/></svg></span>
                            Settings
                        </a>
                    </li>
                    @endif

                    <li class="nav-section">Reports</li>
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                            Reports
                        </a>
                    </li>
                    @if(auth()->user()->role === 'Administrator')
                    <li>
                        <a href="#">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                            Audit Trail
                        </a>
                    </li>
                    @endif
                </ul>
            </nav>
        </aside>

        <!-- TOP NAV -->
        <div class="main-wrapper">
            <header class="topnav">
                <button class="menu-toggle" id="menuToggle" type="button" onclick="toggleSidebar()" aria-label="Toggle menu" aria-controls="sidebar" aria-expanded="false">
                    <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <div class="topnav-branch">
                    @php($currentBranch = auth()->user()->activeBranch())
                    <span class="branch-indicator {{ $currentBranch?->is_headquarters ? 'hq' : 'branch' }}">
                        {{ $currentBranch?->name }}
                    </span>
                </div>

                <div class="topnav-right">
                    <button class="icon-btn" id="themeToggle" type="button" aria-label="Toggle dark mode">
                        <svg viewBox="0 0 24 24"><path d="M12 3v2"/><path d="M12 19v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/><circle cx="12" cy="12" r="3.5"/></svg>
                    </button>
                    <div class="user-menu" id="userMenu">
                        <button class="user-btn" onclick="document.getElementById('userDropdown').classList.toggle('show')">
                            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}</div>
                            <div class="user-info">
                                <span class="user-name">{{ explode(' ', auth()->user()->full_name)[0] }}</span>
                                <span class="user-role">{{ auth()->user()->role }}</span>
                            </div>
                            <svg viewBox="0 0 24 24" class="chevron"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="{{ route('profile.edit') }}">
                                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                My Profile
                            </a>
                            <hr>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <a href="{{ route('logout') }}" class="logout"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                    Logout
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Heading -->
            @isset($header)
                <div class="page-heading">
                    {{ $header }}
                </div>
            @endisset

            <!-- Page Content -->
            <main class="page-content">
                {{ $slot }}
            </main>
        </div>

        <script src="{{ asset('js/app.js') }}"></script>
    </body>
</html>
