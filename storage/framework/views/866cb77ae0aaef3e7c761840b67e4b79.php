<?php $__env->startSection('content'); ?>
<div class="page-fit">
    <header class="page-head">
        <div>
            <h1>Device List</h1>
            <p>Agent-free, administrator-first monitoring. Status comes from the latest stored check.</p>
        </div>
        <form method="GET" class="filter-row">
            <input type="text" name="q" value="<?php echo e(request('q')); ?>" placeholder="Name or address" aria-label="Search devices">
            <select name="status" aria-label="Filter by status">
                <option value="">All states</option>
                <option value="healthy" <?php if(request('status') === 'healthy'): echo 'selected'; endif; ?>>Healthy</option>
                <option value="degraded" <?php if(request('status') === 'degraded'): echo 'selected'; endif; ?>>Degraded</option>
                <option value="critical" <?php if(request('status') === 'critical'): echo 'selected'; endif; ?>>Critical</option>
                <option value="offline" <?php if(request('status') === 'offline'): echo 'selected'; endif; ?>>Offline</option>
                <option value="unknown" <?php if(request('status') === 'unknown'): echo 'selected'; endif; ?>>Waiting</option>
            </select>
            <button class="button button-secondary" type="submit">Filter</button>
        </form>
    </header>

    <?php if($unrecognized->isNotEmpty()): ?>
        <section class="seen-row">
            <h2>Seen, not recognized</h2>
            <?php $__currentLoopData = $unrecognized; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <form method="POST" action="<?php echo e(route('devices.recognize', $device)); ?>" class="seen-item">
                    <?php echo csrf_field(); ?>
                    <span><?php echo e($device->last_ip); ?></span>
                    <button class="button button-secondary" type="submit">Recognize</button>
                </form>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>
    <?php endif; ?>

    <section class="table-panel log-panel">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Computer</th>
                        <th>Status</th>
                        <th>Problem</th>
                        <th>Last seen</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $devices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $device): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr data-href="<?php echo e(route('devices.show', $device)); ?>">
                            <td>
                                <strong><?php echo e($device->display_name); ?></strong>
                                <?php if($device->is_local): ?><em class="row-tag">Central</em><?php endif; ?>
                            </td>
                            <td><?php if (isset($component)) { $__componentOriginal0f0f6d48f1e3fcafba02703e0b070890 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-pill','data' => ['status' => $device->status,'label' => match($device->status) { 'offline' => 'Offline', 'unknown' => 'Waiting', 'degraded' => 'Degraded', 'critical' => 'Critical', 'healthy' => 'Healthy', default => ucfirst($device->status) }]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-pill'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($device->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(match($device->status) { 'offline' => 'Offline', 'unknown' => 'Waiting', 'degraded' => 'Degraded', 'critical' => 'Critical', 'healthy' => 'Healthy', default => ucfirst($device->status) })]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890)): ?>
<?php $attributes = $__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890; ?>
<?php unset($__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0f0f6d48f1e3fcafba02703e0b070890)): ?>
<?php $component = $__componentOriginal0f0f6d48f1e3fcafba02703e0b070890; ?>
<?php unset($__componentOriginal0f0f6d48f1e3fcafba02703e0b070890); ?>
<?php endif; ?></td>
                            <td><?php echo e($device->attentionLine()); ?></td>
                            <td><?php echo e($device->last_seen_at?->diffForHumans() ?? 'Never'); ?></td>
                            <td><?php echo e($device->last_ip ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">No computer matches this filter.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'NetRescue CIT · Devices', 'pageTitle' => 'Managed Devices'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/devices/index.blade.php ENDPATH**/ ?>