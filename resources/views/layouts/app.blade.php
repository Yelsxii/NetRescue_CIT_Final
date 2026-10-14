<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'NetRescue CIT' }}</title>
    <script>
        (() => {
            if (localStorage.getItem('netrescue-sidebar') === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
            if (localStorage.getItem('netrescue-theme') === 'light') {
                document.documentElement.classList.add('theme-light');
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body data-latest-check-id="{{ \App\Models\HealthCheck::query()->latest('checked_at')->value('id') ?? '' }}">
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp
<div class="app-shell" id="appShell">
    <aside class="sidebar" id="sidebar">
        <div class="side-top">
            <button type="button" class="brand-toggle" id="sidebarCollapseButton" aria-label="Collapse menu" title="Collapse menu">
                <span class="menu-lines"><i></i><i></i><i></i></span>
                <span class="brand-copy">
                    <strong>NetRescue</strong>
                    <span>CIT</span>
                </span>
            </button>
        </div>

        <div class="greet">
            <strong>{{ $greeting }}</strong>
            <span>CIT Administrator</span>
        </div>

        <nav class="main-nav" aria-label="Primary navigation">
            <span class="nav-rail" aria-hidden="true"></span>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" title="Dashboard">
                <x-icon name="dashboard" size="18" />
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="{{ route('devices.index') }}" class="nav-link {{ request()->routeIs('devices.*') ? 'is-active' : '' }}" title="Devices">
                <x-icon name="devices" size="18" />
                <span class="nav-label">Devices</span>
            </a>
            <a href="{{ route('history.index') }}" class="nav-link {{ request()->routeIs('history.*') || request()->routeIs('incidents.*') ? 'is-active' : '' }}" title="Incidents">
                <x-icon name="incident" size="18" />
                <span class="nav-label">Incidents</span>
            </a>
            <button class="nav-link nav-button" type="button" data-open-modal="settingsModal" title="Settings">
                <x-icon name="settings" size="18" />
                <span class="nav-label">Settings</span>
            </button>
        </nav>

        <div class="side-spacer"></div>

        <div class="side-user">
            <div class="avatar">CIT</div>
            <div class="side-user-copy">
                <strong>Central console</strong>
                <span>Administrator</span>
            </div>
        </div>
    </aside>

    <button type="button" class="sidebar-scrim" id="sidebarScrim" aria-label="Close menu"></button>

    <main class="main-panel">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-toggle mobile-menu" id="mobileMenuButton" aria-label="Open menu">
                    <span class="menu-lines"><i></i><i></i><i></i></span>
                </button>
                <form action="{{ route('devices.index') }}" method="GET" class="topbar-search">
                    <x-icon name="search" size="16" />
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search a computer or address" aria-label="Search devices">
                </form>
            </div>
            <div class="topbar-actions">
                <time id="appClock" class="app-clock" datetime=""><x-icon name="clock" size="14" /> <b>--:--</b></time>
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Use light mode" title="Light or dark">
                    <x-icon name="sun" size="15" class="icon-sun" />
                    <x-icon name="moon" size="15" class="icon-moon" />
                </button>
                @php($settingsForHeader = \App\Models\MonitorSetting::current())
                <span class="monitor-chip {{ $settingsForHeader->enabled ? 'is-on' : 'is-off' }}">
                    <span></span>{{ $settingsForHeader->enabled ? 'Every '.$settingsForHeader->interval_minutes.' min' : 'Paused' }}
                </span>
            </div>
        </header>

        <section class="content-wrap">
            @isset($slot){{ $slot }}@endisset
            @yield('content')
        </section>
    </main>
</div>

@php($settingsModal = \App\Models\MonitorSetting::current())
<div class="modal-backdrop" id="settingsModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="settingsModalTitle">
        <div class="modal-head">
            <div>
                <span class="eyebrow">Settings</span>
                <h2 id="settingsModalTitle">Monitoring</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal aria-label="Close">×</button>
        </div>
        <form action="{{ route('settings.update') }}" method="POST" class="modal-body js-busy-form" data-busy-text="Saving monitoring settings…">
            @csrf
            @method('PATCH')

            <label class="switch-row">
                <div>
                    <strong>Automatic checks</strong>
                    <span>A check runs when this interval has passed. The clock looks every minute, then measures only if the setting is due.</span>
                </div>
                <span class="switch-control">
                    <input type="checkbox" name="enabled" value="1" {{ old('enabled', $settingsModal->enabled) ? 'checked' : '' }}>
                    <span class="switch-ui"></span>
                </span>
            </label>

            <div class="form-grid">
                <div class="field-group">
                    <label for="interval_minutes">Check every</label>
                    <select id="interval_minutes" name="interval_minutes">
                        @foreach([1,5,10,15,30,60] as $minutes)
                            <option value="{{ $minutes }}" @selected((int) old('interval_minutes', $settingsModal->interval_minutes) === $minutes)>{{ $minutes }} minute{{ $minutes > 1 ? 's' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label for="latency_warning_ms">Slow-response warning</label>
                    <div class="input-suffix">
                        <input id="latency_warning_ms" type="number" min="10" max="1000" name="latency_warning_ms" value="{{ old('latency_warning_ms', $settingsModal->latency_warning_ms) }}" required>
                        <span>ms</span>
                    </div>
                </div>
            </div>

            <div class="field-group">
                <label for="dns_test_domain">Name used for the lookup test</label>
                <input id="dns_test_domain" type="text" name="dns_test_domain" value="{{ old('dns_test_domain', $settingsModal->dns_test_domain) }}" required>
            </div>

            @if($errors->any())
                <div class="form-errors">
                    <strong>Please correct the settings.</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="modal-actions">
                <button type="button" class="button button-ghost" data-close-modal>Cancel</button>
                <button type="submit" class="button button-primary">Save settings</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="recoveryModal" aria-hidden="true">
    <div class="modal-card modal-sm" role="dialog" aria-modal="true" aria-labelledby="recoveryModalTitle">
        <div class="modal-head">
            <div>
                <span class="eyebrow">Approved repair</span>
                <h2 id="recoveryModalTitle">Run the safe repair?</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal aria-label="Close">×</button>
        </div>
        <div class="modal-body">
            <p id="recoveryModalText">The approved repair runs on the computer this incident belongs to, then the result is checked.</p>
            <form method="POST" id="recoveryModalForm" class="js-busy-form" data-busy-text="Running the approved repair and checking the result…">
                @csrf
                <div class="modal-actions">
                    <button type="button" class="button button-ghost" data-close-modal>Cancel</button>
                    <button type="submit" class="button button-primary">Run repair</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="recordModal" aria-hidden="true">
    <div class="modal-card record-modal" role="dialog" aria-modal="true" aria-labelledby="recordModalTitle">
        <button class="icon-button record-close" type="button" data-close-modal aria-label="Close">×</button>
        <div id="recordModalBody"></div>
    </div>
</div>

<div class="busy-overlay" id="busyOverlay" aria-hidden="true">
    <div class="busy-card">
        <span class="spinner"></span>
        <strong id="busyTitle">Working…</strong>
        <span>NetRescue is checking the result.</span>
    </div>
</div>

<div class="toast-stack" id="toastStack">
    @if(session('toast'))
        @php($toast = session('toast'))
        <div class="toast toast-{{ $toast['type'] ?? 'success' }}" data-toast>
            <div>
                <strong>{{ $toast['title'] ?? 'Done' }}</strong>
                <span>{{ $toast['message'] ?? '' }}</span>
            </div>
            <button type="button" data-dismiss-toast aria-label="Dismiss">×</button>
        </div>
    @endif
</div>

<script src="{{ asset('js/app.js') }}" defer></script>
@if($errors->any())
<script>window.addEventListener('DOMContentLoaded',()=>window.NetRescue?.openModal('settingsModal'));</script>
@endif
</body>
</html>
