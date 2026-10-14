<?php $__env->startSection('content'); ?>
<div class="page-fit dashboard">
    <header class="dash-head">
        <div class="dash-copy">
            <h1><?php echo e($summary['fleet_state']['title']); ?></h1>
            <p><?php echo e($summary['fleet_state']['message']); ?></p>
        </div>
        <div class="dash-actions">
            <?php if($settings->enabled && $nextCheckAt): ?>
                <span class="next-chip">Next check <b data-next-check="<?php echo e($nextCheckAt->toIso8601String()); ?>">…</b></span>
            <?php endif; ?>
            <form action="<?php echo e(route('health-checks.store')); ?>" method="POST" class="js-busy-form" data-busy-text="Checking the Central computer network…">
                <?php echo csrf_field(); ?>
                <button class="button button-primary" type="submit"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'refresh','size' => '15']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'refresh','size' => '15']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Run Central Check</button>
            </form>
        </div>
    </header>

    <section class="stat-row" aria-label="Current totals">
        <article class="stat-card stat-a">
            <span class="stat-mark"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'online','size' => '22']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'online','size' => '22']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
            <div class="stat-copy">
                <span>Reporting</span>
                <strong><?php echo e($summary['stats']['online']); ?></strong>
                <small>out of <?php echo e($summary['stats']['total']); ?></small>
            </div>
        </article>
        <article class="stat-card stat-b">
            <span class="stat-mark"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'offline','size' => '22']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'offline','size' => '22']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
            <div class="stat-copy">
                <span>Not reporting</span>
                <strong><?php echo e($summary['stats']['offline']); ?></strong>
                <small>out of <?php echo e($summary['stats']['total']); ?></small>
            </div>
        </article>
        <article class="stat-card stat-c">
            <span class="stat-mark"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'shield','size' => '22']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield','size' => '22']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
            <div class="stat-copy">
                <span>Healthy · 24h</span>
                <strong><?php echo e($summary['stats']['healthy_check_rate_24h'] !== null ? $summary['stats']['healthy_check_rate_24h'].'%' : '—'); ?></strong>
                <small>out of <?php echo e($summary['stats']['checks_24h']); ?> checks</small>
            </div>
        </article>
        <article class="stat-card stat-d">
            <span class="stat-mark"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'incident','size' => '22']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'incident','size' => '22']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
            <div class="stat-copy">
                <span>Open incidents</span>
                <strong><?php echo e($summary['stats']['active_incidents']); ?></strong>
                <small><?php echo e($summary['problem_groups']->isEmpty() ? 'None open' : $summary['problem_groups']->count().' grouped '.\Illuminate\Support\Str::plural('problem', $summary['problem_groups']->count())); ?></small>
            </div>
        </article>
    </section>

    <?php
        $window = $summary['response_points']->slice(-48)->values();
        $warn = (int) $settings->latency_warning_ms;
        $marker = $networkScale > 0 ? min(100, ($warn / $networkScale) * 100) : 100;
        $scaleEnd = rtrim(rtrim(number_format((float) $networkScale, 1, '.', ''), '0'), '.');
        $outside = function ($ok, $ms) use ($warn) {
            return $ok === false || (is_numeric($ms) && (float) $ms > $warn);
        };
        $tally = [
            ['label' => 'Gateway', 'slow' => $window->filter(fn ($row) => $outside($row->gateway_ok ?? null, $row->gateway ?? null))->count()],
            ['label' => 'Internet', 'slow' => $window->filter(fn ($row) => $outside($row->internet_ok ?? null, $row->internet ?? null))->count()],
            ['label' => 'Name lookup', 'slow' => $window->filter(fn ($row) => $outside($row->dns_ok ?? null, $row->dns ?? null))->count()],
        ];
    ?>
    <section class="workspace">
        <article class="chart-panel">
            <div class="panel-title">
                <div>
                    <h2>Response times</h2>
                    <p><?php echo e($warn); ?> ms warning threshold</p>
                </div>
            </div>
            <?php if($measuredCheck): ?>
                <?php if($readingIncomplete): ?>
                    <p class="quiet">The latest check did not finish. These bars are the last completed reading.</p>
                <?php endif; ?>
                <div class="chart-fill">
                <div class="meter-list">
                    <?php $__currentLoopData = $networkNow; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $mark = \App\Support\CheckStory::mark($row['ok'], $row['ms'], $warn);
                        ?>
                        <div class="meter">
                            <div class="meter-top">
                                <span><?php echo e($row['label']); ?></span>
                                <b class="mark-<?php echo e($mark['code']); ?>"><?php echo e($mark['label']); ?> <?php echo e($mark['detail']); ?></b>
                            </div>
                            <div class="meter-track">
                                <i class="<?php echo e($mark['code'] === 'fail' ? 'is-fail' : ($mark['code'] === 'slow' ? 'is-slow' : ($mark['code'] === 'none' ? 'is-none' : ''))); ?>" style="width: <?php echo e($row['width']); ?>%"></i>
                                <em class="meter-warn" style="left: <?php echo e($marker); ?>%"></em>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <div class="meter-scale">
                        <span>0 ms</span>
                        <span><?php echo e($scaleEnd); ?> ms</span>
                    </div>
                </div>
                <div class="tally" aria-label="Slow or failed checks">
                    <p class="tally-label">Slow or failed checks</p>
                    <?php $__currentLoopData = $tally; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <article>
                            <span><?php echo e($item['label']); ?></span>
                            <strong class="<?php echo e($item['slow'] > 0 ? 'mark-slow' : 'mark-pass'); ?>"><?php echo e($item['slow']); ?></strong>
                            <small>of <?php echo e($window->count()); ?> <?php echo e(\Illuminate\Support\Str::plural('check', $window->count())); ?></small>
                        </article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                </div>
            <?php else: ?>
                <div class="empty-chart">
                    <strong>No measurement yet</strong>
                    <span>Run Central Check to compare this computer with the warning.</span>
                </div>
            <?php endif; ?>
        </article>

        <article class="now-panel">
            <div class="panel-title compact">
                <div>
                    <h2>Now</h2>
                    <p><?php echo e($localLatest ? $localLatest->checked_at->diffForHumans() : 'No measurement yet'); ?></p>
                </div>
            </div>
            <?php if($localLatest): ?>
                <?php
                    $openHere = $summary['active_incidents']->where('device_id', $localDevice->id);
                    $shown = $measuredCheck ?: $localLatest;
                ?>
                <div class="now-facts">
                    <p class="case-fact"><?php echo e($localLatest->diagnosis_title); ?></p>
                    <?php if($readingIncomplete): ?>
                        <p class="quiet"><?php echo e($localLatest->diagnosis_summary); ?></p>
                    <?php endif; ?>
                    <?php if($measuredCheck): ?>
                        <p class="now-line"><span>Address</span> <b><?php echo e($shown->local_ip ?: 'None'); ?></b></p>
                        <p class="now-line"><span>Gateway</span> <b><?php echo e($shown->gateway_ip ?: 'None'); ?></b></p>
                        <p class="now-line"><span>Adapter</span> <b><?php echo e($shown->interface_name ?: 'Unknown'); ?></b></p>
                        <p class="now-line"><span>DNS</span> <b><?php echo e(is_array($shown->dns_servers) && $shown->dns_servers ? implode(', ', $shown->dns_servers) : 'None recorded'); ?></b></p>
                        <?php if($readingIncomplete): ?>
                            <p class="quiet">Last completed reading <?php echo e($measuredCheck->checked_at->diffForHumans()); ?>.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="quiet">No completed reading is stored yet.</p>
                    <?php endif; ?>
                </div>
                <?php $__empty_1 = true; $__currentLoopData = $openHere; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $incident): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="repair-slot">
                        <strong>Available repair</strong>
                        <?php if($incident->canRepair()): ?>
                            <span><?php echo e($incident->repairCommand()); ?></span>
                            <form method="POST" action="<?php echo e(route('incidents.recover', $incident)); ?>" class="js-busy-form" data-busy-text="Running the repair and measuring again…">
                                <?php echo csrf_field(); ?>
                                <button class="button button-primary" type="submit">Run repair</button>
                            </form>
                        <?php else: ?>
                            <span>No repair command is available for this condition.</span>
                        <?php endif; ?>
                        <?php if($incident->nextStep()): ?><p class="quiet"><?php echo e($incident->nextStep()); ?></p><?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <?php endif; ?>
            <?php else: ?>
                <p class="quiet">Run Central Check to measure this computer.</p>
            <?php endif; ?>
        </article>

        <article class="table-panel log-panel">
            <div class="panel-title compact">
                <div><h2>Latest checks</h2></div>
                <a href="<?php echo e(route('devices.checks', $localDevice)); ?>">All checks</a>
            </div>
            <?php if (isset($component)) { $__componentOriginal012f09529ea5180720457b0b853b2950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal012f09529ea5180720457b0b853b2950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.check-table','data' => ['checks' => $recentChecks,'warning' => $settings->latency_warning_ms]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('check-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['checks' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($recentChecks),'warning' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($settings->latency_warning_ms)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal012f09529ea5180720457b0b853b2950)): ?>
<?php $attributes = $__attributesOriginal012f09529ea5180720457b0b853b2950; ?>
<?php unset($__attributesOriginal012f09529ea5180720457b0b853b2950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal012f09529ea5180720457b0b853b2950)): ?>
<?php $component = $__componentOriginal012f09529ea5180720457b0b853b2950; ?>
<?php unset($__componentOriginal012f09529ea5180720457b0b853b2950); ?>
<?php endif; ?>
        </article>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'NetRescue CIT · Dashboard', 'pageTitle' => 'Dashboard'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/dashboard/index.blade.php ENDPATH**/ ?>