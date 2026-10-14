<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title ?? 'NetRescue CIT'); ?></title>
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
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
</head>
<body data-latest-check-id="<?php echo e(\App\Models\HealthCheck::query()->latest('checked_at')->value('id') ?? ''); ?>">
<?php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>
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
            <strong><?php echo e($greeting); ?></strong>
            <span>CIT Administrator</span>
        </div>

        <nav class="main-nav" aria-label="Primary navigation">
            <span class="nav-rail" aria-hidden="true"></span>
            <a href="<?php echo e(route('dashboard')); ?>" class="nav-link <?php echo e(request()->routeIs('dashboard') ? 'is-active' : ''); ?>" title="Dashboard">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'dashboard','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'dashboard','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?php echo e(route('devices.index')); ?>" class="nav-link <?php echo e(request()->routeIs('devices.*') ? 'is-active' : ''); ?>" title="Devices">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'devices','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'devices','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                <span class="nav-label">Devices</span>
            </a>
            <a href="<?php echo e(route('history.index')); ?>" class="nav-link <?php echo e(request()->routeIs('history.*') || request()->routeIs('incidents.*') ? 'is-active' : ''); ?>" title="Incidents">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'incident','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'incident','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                <span class="nav-label">Incidents</span>
            </a>
            <button class="nav-link nav-button" type="button" data-open-modal="settingsModal" title="Settings">
                <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'settings','size' => '18']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'settings','size' => '18']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
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
                <form action="<?php echo e(route('devices.index')); ?>" method="GET" class="topbar-search">
                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'search','size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'search','size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                    <input type="text" name="q" value="<?php echo e(request('q')); ?>" placeholder="Search a computer or address" aria-label="Search devices">
                </form>
            </div>
            <div class="topbar-actions">
                <time id="appClock" class="app-clock" datetime=""><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'clock','size' => '14']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'clock','size' => '14']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <b>--:--</b></time>
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Use light mode" title="Light or dark">
                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'sun','size' => '15','class' => 'icon-sun']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sun','size' => '15','class' => 'icon-sun']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'moon','size' => '15','class' => 'icon-moon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'moon','size' => '15','class' => 'icon-moon']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
                </button>
                <?php ($settingsForHeader = \App\Models\MonitorSetting::current()); ?>
                <span class="monitor-chip <?php echo e($settingsForHeader->enabled ? 'is-on' : 'is-off'); ?>">
                    <span></span><?php echo e($settingsForHeader->enabled ? 'Every '.$settingsForHeader->interval_minutes.' min' : 'Paused'); ?>

                </span>
            </div>
        </header>

        <section class="content-wrap">
            <?php if(isset($slot)): ?><?php echo e($slot); ?><?php endif; ?>
            <?php echo $__env->yieldContent('content'); ?>
        </section>
    </main>
</div>

<?php ($settingsModal = \App\Models\MonitorSetting::current()); ?>
<div class="modal-backdrop" id="settingsModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="settingsModalTitle">
        <div class="modal-head">
            <div>
                <span class="eyebrow">Settings</span>
                <h2 id="settingsModalTitle">Monitoring</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal aria-label="Close">×</button>
        </div>
        <form action="<?php echo e(route('settings.update')); ?>" method="POST" class="modal-body js-busy-form" data-busy-text="Saving monitoring settings…">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PATCH'); ?>

            <label class="switch-row">
                <div>
                    <strong>Automatic checks</strong>
                    <span>A check runs when this interval has passed. The clock looks every minute, then measures only if the setting is due.</span>
                </div>
                <span class="switch-control">
                    <input type="checkbox" name="enabled" value="1" <?php echo e(old('enabled', $settingsModal->enabled) ? 'checked' : ''); ?>>
                    <span class="switch-ui"></span>
                </span>
            </label>

            <div class="form-grid">
                <div class="field-group">
                    <label for="interval_minutes">Check every</label>
                    <select id="interval_minutes" name="interval_minutes">
                        <?php $__currentLoopData = [1,5,10,15,30,60]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $minutes): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($minutes); ?>" <?php if((int) old('interval_minutes', $settingsModal->interval_minutes) === $minutes): echo 'selected'; endif; ?>><?php echo e($minutes); ?> minute<?php echo e($minutes > 1 ? 's' : ''); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="field-group">
                    <label for="latency_warning_ms">Slow-response warning</label>
                    <div class="input-suffix">
                        <input id="latency_warning_ms" type="number" min="10" max="1000" name="latency_warning_ms" value="<?php echo e(old('latency_warning_ms', $settingsModal->latency_warning_ms)); ?>" required>
                        <span>ms</span>
                    </div>
                </div>
            </div>

            <div class="field-group">
                <label for="dns_test_domain">Name used for the lookup test</label>
                <input id="dns_test_domain" type="text" name="dns_test_domain" value="<?php echo e(old('dns_test_domain', $settingsModal->dns_test_domain)); ?>" required>
            </div>

            <?php if($errors->any()): ?>
                <div class="form-errors">
                    <strong>Please correct the settings.</strong>
                    <ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
                </div>
            <?php endif; ?>

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
                <?php echo csrf_field(); ?>
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
    <?php if(session('toast')): ?>
        <?php ($toast = session('toast')); ?>
        <div class="toast toast-<?php echo e($toast['type'] ?? 'success'); ?>" data-toast>
            <div>
                <strong><?php echo e($toast['title'] ?? 'Done'); ?></strong>
                <span><?php echo e($toast['message'] ?? ''); ?></span>
            </div>
            <button type="button" data-dismiss-toast aria-label="Dismiss">×</button>
        </div>
    <?php endif; ?>
</div>

<script src="<?php echo e(asset('js/app.js')); ?>" defer></script>
<?php if($errors->any()): ?>
<script>window.addEventListener('DOMContentLoaded',()=>window.NetRescue?.openModal('settingsModal'));</script>
<?php endif; ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/layouts/app.blade.php ENDPATH**/ ?>