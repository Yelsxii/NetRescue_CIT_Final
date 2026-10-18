<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['checks', 'warning' => 100]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['checks', 'warning' => 100]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<p class="table-scroll-hint"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'chevron','size' => '14']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron','size' => '14']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Scroll to see all columns</p>
<div class="table-scroll" role="region" aria-label="Network check history" tabindex="0">
    <table class="data-table check-table">
        <colgroup><col style="width:11%"><col style="width:21%"><col style="width:21%"><col style="width:22%"><col style="width:25%"></colgroup>
        <thead>
            <tr>
                <th scope="col">Time</th>
                <th scope="col">Gateway</th>
                <th scope="col">Internet</th>
                <th scope="col">Name lookup</th>
                <th scope="col" class="result-cell">Result</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $checks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $check): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $resultTone = $check->diagnosis_code === 'healthy' ? 'ok' : (in_array($check->severity, ['none', 'low'], true) ? 'warn' : 'bad');
                    $gateway = \App\Support\CheckStory::probeMark($check, 'gateway', (int) $warning);
                    $internet = \App\Support\CheckStory::probeMark($check, 'internet', (int) $warning);
                    $dns = \App\Support\CheckStory::probeMark($check, 'dns', (int) $warning);
                ?>
                <tr>
                    <td><?php echo e($check->checked_at->format('g:i A')); ?></td>
                    <td><b class="mark-<?php echo e($gateway['code']); ?>"><?php echo e($gateway['label']); ?></b> <?php echo e($gateway['detail']); ?></td>
                    <td><b class="mark-<?php echo e($internet['code']); ?>"><?php echo e($internet['label']); ?></b> <?php echo e($internet['detail']); ?></td>
                    <td><b class="mark-<?php echo e($dns['code']); ?>"><?php echo e($dns['label']); ?></b> <?php echo e($dns['detail']); ?></td>
                    <td class="result-cell"><span class="result-text is-<?php echo e($resultTone); ?>"><?php echo e($check->diagnosis_title); ?></span></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="5">No check is stored yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/components/check-table.blade.php ENDPATH**/ ?>