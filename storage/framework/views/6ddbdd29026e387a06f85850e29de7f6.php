<?php $__env->startSection('content'); ?>
<div class="page-fit">
    <header class="page-head">
        <div>
            <h1>Incidents</h1>
            <p>Incidents on this computer, with observed outcomes and repair verification.</p>
        </div>
        <form method="GET" class="filter-row">
            <select name="status" aria-label="Filter by status">
                <option value="">Any status</option>
                <option value="active" <?php if(request('status') === 'active'): echo 'selected'; endif; ?>>Open</option>
                <option value="resolved" <?php if(request('status') === 'resolved'): echo 'selected'; endif; ?>>Closed</option>
            </select>
            <select name="severity" aria-label="Filter by severity">
                <option value="">Any severity</option>
                <?php $__currentLoopData = ['critical','high','moderate','low']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $severity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($severity); ?>" <?php if(request('severity') === $severity): echo 'selected'; endif; ?>><?php echo e(ucfirst($severity)); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <button class="button button-secondary" type="submit">Apply</button>
        </form>
    </header>

    <section class="table-panel log-panel">
        <div class="table-scroll">
            <table class="data-table incident-table">
                <colgroup><col style="width:23%"><col style="width:17%"><col style="width:12%"><col style="width:38%"><col style="width:10%"></colgroup>
                <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Started</th>
                        <th>Duration</th>
                        <th>Outcome and repair</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $incidents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $incident): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php ($story = $incident->story()); ?>
                        <tr data-href="<?php echo e(route('incidents.show', $incident)); ?>">
                            <td><?php echo e($incident->plainProblem()); ?></td>
                            <td><?php echo e($incident->first_detected_at->format('M j, g:i:s A')); ?></td>
                            <td><?php echo e($incident->durationLabel()); ?></td>
                            <td class="incident-resolution"><p data-story-resolution><?php echo e($story['resolution']); ?></p><p class="quiet" data-story-repair><?php echo e($story['repair']); ?></p></td>
                            <td><?php if (isset($component)) { $__componentOriginal0f0f6d48f1e3fcafba02703e0b070890 = $component; } ?>
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
<?php endif; ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5">No incident matches this filter.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($incidents->hasPages()): ?>
            <div class="pagination-wrap"><?php echo e($incidents->links('partials.pagination')); ?></div>
        <?php endif; ?>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'NetRescue CIT · Incidents', 'pageTitle' => 'Incidents'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/history/index.blade.php ENDPATH**/ ?>