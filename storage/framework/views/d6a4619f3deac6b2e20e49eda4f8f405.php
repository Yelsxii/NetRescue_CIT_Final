<?php
    $openedFact = \App\Support\CheckStory::focus($incident->code, $openingCheck, $warningMs) ?: $incident->plainDetail();
    $clearedFact = \App\Support\CheckStory::focus($incident->code, $closingCheck, $warningMs);
?>
<header class="record-head">
    <div>
        <p class="record-kicker"><?php echo e($incident->reference); ?> · This computer</p>
        <h2 id="recordModalTitle"><?php echo e($incident->plainProblem()); ?></h2>
        <p class="record-meta">
            <?php if (isset($component)) { $__componentOriginal0f0f6d48f1e3fcafba02703e0b070890 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-pill','data' => ['status' => $incident->status,'label' => $incident->status === 'resolved' ? 'Closed' : 'Open']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-pill'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($incident->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($incident->status === 'resolved' ? 'Closed' : 'Open')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890)): ?>
<?php $attributes = $__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890; ?>
<?php unset($__attributesOriginal0f0f6d48f1e3fcafba02703e0b070890); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0f0f6d48f1e3fcafba02703e0b070890)): ?>
<?php $component = $__componentOriginal0f0f6d48f1e3fcafba02703e0b070890; ?>
<?php unset($__componentOriginal0f0f6d48f1e3fcafba02703e0b070890); ?>
<?php endif; ?>
            <span>Opened <?php echo e($incident->first_detected_at->format('g:i:s A')); ?></span>
            <?php if($incident->resolved_at): ?><span>Closed <?php echo e($incident->resolved_at->format('g:i:s A')); ?></span><?php endif; ?>
            <span><?php echo e($incident->durationLabel()); ?></span>
        </p>
    </div>
</header>
<?php echo $__env->make('incidents.case', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/incidents/panel.blade.php ENDPATH**/ ?>