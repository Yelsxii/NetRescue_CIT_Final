<?php
    $story = $incident->story();
    $attempt = $incident->latestRecovery();
?>
<section class="detail-stack record-grid">
    <article class="case-card">
        <h2>Detected condition</h2>
        <?php if($openingCheck): ?>
            <p class="case-when"><?php echo e(\App\Support\CheckStory::sourceLabel($openingCheck)); ?> · <?php echo e($openingCheck->checked_at->format('g:i:s A')); ?></p>
            <p class="case-fact"><?php echo e($openedFact); ?></p>
            <?php if($incident->code === 'dhcp_issue'): ?>
                <p class="quiet">This is an automatic fallback address. No normal DHCP address was obtained in this reading.</p>
            <?php endif; ?>
            <p class="quiet">Gateway: <?php echo e($openingCheck->gateway_ip ?: 'None reported'); ?> · Adapter: <?php echo e($openingCheck->interface_name ?: 'Unknown'); ?></p>
        <?php else: ?>
            <p class="case-fact"><?php echo e($incident->plainDetail()); ?></p>
            <p class="quiet">The exact opening measurement is not available for this record.</p>
        <?php endif; ?>
    </article>

    <article class="case-card">
        <h2>Repair request and result</h2>
        <p class="case-fact" data-story-repair><?php echo e($story['repair']); ?></p>
        <?php if($incident->canRepair()): ?>
            <p class="quiet">Available action: <code><?php echo e($incident->repairCommand()); ?></code></p>
            <p class="quiet"><?php echo e($incident->device?->is_local ? 'Checks the condition before deciding whether to run a command.' : 'Queues a command for the endpoint; completion must be reported separately.'); ?></p>
            <form method="POST" action="<?php echo e(route('incidents.recover', $incident)); ?>" class="js-busy-form" data-busy-text="Checking the current condition and verifying the repair outcome...">
                <?php echo csrf_field(); ?>
                <button class="button button-primary" type="submit">Run repair</button>
            </form>
        <?php elseif($incident->is_active && filled($incident->repairCommand()) && $incident->device && ! $incident->device->is_local): ?>
            <p class="quiet">This computer is not reporting, so the command cannot be sent.</p>
        <?php elseif($incident->is_active): ?>
            <p class="quiet">No repair command is available for this condition.</p>
        <?php endif; ?>
        <?php if($incident->is_active && $incident->nextStep()): ?>
            <p class="next-step"><b>Next step:</b> <?php echo e($incident->nextStep()); ?></p>
        <?php endif; ?>
    </article>

    <article class="case-card">
        <h2><?php echo e($incident->is_active ? 'Current outcome' : 'Resolution observed'); ?></h2>
        <p class="case-fact" data-story-resolution><?php echo e($story['resolution']); ?></p>
        <?php if(! $incident->is_active && $attempt?->beforeHealthCheck?->diagnosis_code === 'healthy' && $attempt->before_health_check_id !== $closingCheck?->id): ?>
            <p class="case-when">Check before repair · <?php echo e($attempt->beforeHealthCheck->checked_at->format('g:i:s A')); ?></p>
            <p class="quiet">Already healthy: IPv4 <?php echo e($attempt->beforeHealthCheck->local_ip ?: 'None'); ?> · Gateway <?php echo e($attempt->beforeHealthCheck->gateway_ip ?: 'None'); ?>.</p>
        <?php endif; ?>
        <?php if(! $incident->is_active && $closingCheck): ?>
            <p class="case-when"><?php echo e(\App\Support\CheckStory::sourceLabel($closingCheck)); ?> · <?php echo e($closingCheck->checked_at->format('g:i:s A')); ?></p>
            <p class="quiet"><?php echo e($clearedFact); ?></p>
            <p class="quiet">Gateway: <?php echo e($closingCheck->gateway_ip ?: 'None reported'); ?> · Diagnosis: <?php echo e($closingCheck->diagnosis_title ?: $closingCheck->diagnosis_code); ?></p>
        <?php endif; ?>
    </article>
</section>
<?php if($attempt): ?>
    <details class="repair-evidence">
        <summary>Measurements and command details</summary>
        <p class="quiet">Command: <?php echo e($attempt->executionLabel()); ?>. Outcome: <?php echo e($attempt->verificationLabel()); ?>.</p>
        <?php if($attempt->command_exit_code !== null): ?><p class="quiet">Command exit code: <?php echo e($attempt->command_exit_code); ?></p><?php endif; ?>
        <?php $__currentLoopData = ['Before' => $attempt->beforeHealthCheck, 'After' => $attempt->afterHealthCheck]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $evidence): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($evidence): ?>
                <p><b><?php echo e($stage); ?>:</b> <?php echo e($evidence->checked_at->format('g:i:s A')); ?> · <?php echo e($evidence->diagnosis_title ?: $evidence->diagnosis_code); ?> · IPv4 <?php echo e($evidence->local_ip ?: 'None'); ?> · Gateway <?php echo e($evidence->gateway_ip ?: 'None'); ?> · <?php echo e($evidence->interface_name ?: 'Unknown adapter'); ?></p>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($attempt->error_message): ?><p class="command-error"><?php echo e($attempt->error_message); ?></p><?php endif; ?>
        <?php if($attempt->output_excerpt): ?><p class="quiet"><b>Stored command output (excerpt)</b></p><pre class="command-output"><?php echo e($attempt->output_excerpt); ?></pre><?php endif; ?>
    </details>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/incidents/case.blade.php ENDPATH**/ ?>