<?php $__env->startSection('content'); ?>
<div class="page-fit">
    <header class="page-head">
        <div>
            <h1>Checks</h1>
            <p>Saved network measurements from this computer.</p>
        </div>
    </header>
    <section class="table-panel log-panel">
        <?php if (isset($component)) { $__componentOriginal012f09529ea5180720457b0b853b2950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal012f09529ea5180720457b0b853b2950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.check-table','data' => ['checks' => $checks,'warning' => $warningMs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('check-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['checks' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($checks),'warning' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($warningMs)]); ?>
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
        <?php if($checks->hasPages()): ?>
            <div class="pagination-wrap"><?php echo e($checks->links('partials.pagination')); ?></div>
        <?php endif; ?>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'NetRescue CIT · Checks'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/checks/index.blade.php ENDPATH**/ ?>