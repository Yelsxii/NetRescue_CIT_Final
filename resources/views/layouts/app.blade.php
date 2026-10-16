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
<body data-latest-check-id="{{ \App\Models\HealthCheck::where('device_id', \App\Models\Device::localHost()->id)->latest('checked_at')->orderByDesc('id')->value('id') ?? '' }}">
<div class="app-shell" id="appShell">
    <aside class="sidebar" id="sidebar" aria-label="Navigation menu">
        <div class="side-top">
            <button type="button" class="brand-toggle" id="sidebarCollapseButton" aria-label="Collapse menu" aria-controls="sidebar" aria-expanded="true" title="Collapse menu">
                <img class="brand-logo" src="{{ asset('images/netrescue-logo.png') }}" alt="NetRescue">
                <img class="brand-mark" src="{{ asset('images/netrescue-mark.png') }}" alt="">
            </button>
            <button type="button" class="icon-button sidebar-close" id="sidebarCloseButton" aria-label="Close menu">
                <x-icon name="close" size="18" />
            </button>
        </div>

        <nav class="main-nav" aria-label="Primary navigation">
            <span class="nav-rail" aria-hidden="true"></span>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" title="Dashboard">
                <x-icon name="dashboard" size="18" />
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="{{ route('checks.index') }}" class="nav-link {{ request()->routeIs('checks.*') ? 'is-active' : '' }}" title="Checks">
                <x-icon name="checks" size="18" />
                <span class="nav-label">Checks</span>
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
    </aside>

    <button type="button" class="sidebar-scrim" id="sidebarScrim" aria-label="Close menu" tabindex="-1"></button>

    <main class="main-panel">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-toggle mobile-menu" id="mobileMenuButton" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
                    <span class="menu-lines" aria-hidden="true"><i></i><i></i><i></i></span>
                </button>
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
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="settingsModalTitle" tabindex="-1">
        <div class="modal-head">
            <div>
                <span class="eyebrow">Settings</span>
                <h2 id="settingsModalTitle">Monitoring</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal aria-label="Close settings"><x-icon name="close" size="18" /></button>
        </div>
        <form action="{{ route('settings.update') }}" method="POST" class="modal-body js-busy-form" data-busy-text="Saving monitoring settings…">
            @csrf
            @method('PATCH')

            <label class="switch-row">
                <div>
                    <strong>Automatic checks</strong>
                    <span>Checks this computer at the selected interval while the scheduler is running.</span>
                </div>
                <span class="switch-control">
                    <input type="checkbox" name="enabled" value="1" {{ old('enabled', $settingsModal->enabled) ? 'checked' : '' }}>
                    <span class="switch-ui"></span>
                </span>
            </label>

            <div class="form-grid">
                <div class="field-group">
                    <label for="interval_minutes">Check every</label>
                    <select id="interval_minutes" name="interval_minutes" data-menu-select>
                        @foreach([1,5,10,15,30,60] as $minutes)
                            <option value="{{ $minutes }}" @selected((int) old('interval_minutes', $settingsModal->interval_minutes) === $minutes)>{{ $minutes }} minute{{ $minutes > 1 ? 's' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-group">
                    <label for="latency_warning_ms">Slow-response warning</label>
                    <div class="number-stepper" data-number-stepper>
                        <button type="button" class="number-stepper-button" data-step-number="-1" aria-label="Decrease slow-response warning by 1 millisecond" aria-controls="latency_warning_ms"><x-icon name="minus" size="16" /></button>
                        <div class="number-stepper-value">
                            <input id="latency_warning_ms" type="number" min="10" max="1000" step="1" name="latency_warning_ms" value="{{ old('latency_warning_ms', $settingsModal->latency_warning_ms) }}" aria-describedby="latencyWarningUnit" required>
                            <span class="number-stepper-unit" id="latencyWarningUnit">ms</span>
                        </div>
                        <button type="button" class="number-stepper-button" data-step-number="1" aria-label="Increase slow-response warning by 1 millisecond" aria-controls="latency_warning_ms"><x-icon name="plus" size="16" /></button>
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
    <div class="modal-card modal-sm" role="dialog" aria-modal="true" aria-labelledby="recoveryModalTitle" tabindex="-1">
        <div class="modal-head">
            <div>
                <span class="eyebrow">Approved repair</span>
                <h2 id="recoveryModalTitle">Run the safe repair?</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal aria-label="Close repair dialog"><x-icon name="close" size="18" /></button>
        </div>
        <div class="modal-body">
            <p id="recoveryModalText">The approved repair runs on this computer, then its result is verified automatically.</p>
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
    <div class="modal-card record-modal" role="dialog" aria-modal="true" aria-labelledby="recordModalTitle" aria-label="Incident details" tabindex="-1">
        <button class="icon-button record-close" type="button" data-close-modal aria-label="Close incident details"><x-icon name="close" size="18" /></button>
        <div id="recordModalBody"></div>
    </div>
</div>

<div class="busy-overlay" id="busyOverlay" aria-hidden="true">
    <div class="busy-card">
        <span class="spinner"></span>
        <strong id="busyTitle">Working…</strong>
        <span id="busyDetail">Waiting for the operation to finish.</span>
        <small id="busyElapsed" aria-live="off"></small>
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
