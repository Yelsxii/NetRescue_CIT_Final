<?php
    $story = $incident->story();
    $attempt = $incident->latestRecovery();
    $needsResultCheck = $incident->is_active && $attempt
        && in_array($attempt->verification_status, ['unread', 'adapter_changed'], true);
    $previousAttempts = $attempt
        ? $incident->recoveryActions()->where('id', '!=', $attempt->id)->with(['beforeHealthCheck', 'afterHealthCheck'])->get()
        : collect();
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

    <article class="case-card case-repair-card">
        <div class="case-card-head">
            <h2><?php echo e($attempt ? 'Latest repair request and result' : 'Repair request and result'); ?></h2>
            <?php if($needsResultCheck): ?>
                <form method="POST" action="<?php echo e(route('incidents.retest', $incident)); ?>" class="js-busy-form case-repair-action" data-busy-text="Checking this incident's adapter again…">
                    <?php echo csrf_field(); ?>
                    <button class="button button-primary" type="submit">Check result</button>
                </form>
            <?php elseif($incident->canRepair()): ?>
                <form method="POST" action="<?php echo e(route('incidents.recover', $incident)); ?>" class="js-busy-form case-repair-action" data-busy-text="Checking the current condition and verifying the repair outcome...">
                    <?php echo csrf_field(); ?>
                    <button class="button button-primary" type="submit">Run repair</button>
                </form>
            <?php endif; ?>
        </div>
        <p class="case-fact" data-story-repair><?php echo e($story['repair']); ?></p>
        <?php if($needsResultCheck): ?>
            <p class="quiet">Check result reads this incident's adapter again without repeating the repair command.</p>
        <?php elseif($incident->canRepair()): ?>
            <p class="quiet">Available action: <code><?php echo e($incident->repairCommand()); ?></code></p>
            <p class="quiet">Checks the current condition on this computer, runs the command if needed, then verifies automatically. Scheduled checks may stay paused.</p>
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
        <?php if(! $incident->is_active && $attempt?->verification_status === 'unread' && $attempt->afterHealthCheck && $closingCheck && $attempt->after_health_check_id !== $closingCheck->id): ?>
            <p class="quiet">Automatic verification ended at <?php echo e($attempt->afterHealthCheck->checked_at->format('g:i:s A')); ?> without a completed reading. The separate closing measurement is shown below.</p>
        <?php endif; ?>
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
        <summary>Latest request: measurements and command details</summary>
        <p class="case-when">Request recorded <?php echo e($attempt->started_at?->format('M j, g:i:s A')); ?></p>
        <p class="quiet">Command: <?php echo e($attempt->executionLabel()); ?>. Outcome: <?php echo e($attempt->verificationLabel()); ?>.</p>
        <?php if($attempt->command_exit_code !== null): ?><p class="quiet">Command exit code: <?php echo e($attempt->command_exit_code); ?></p><?php endif; ?>
        <?php if($verification = data_get($attempt->afterHealthCheck?->probe_meta, 'recovery_verification')): ?>
            <?php if(($verification['retry_policy'] ?? null) === 'reconnect_window'): ?>
                <p class="quiet">Automatic verification: <?php echo e($verification['attempt']); ?> readings; reconnect window up to <?php echo e(number_format($verification['reconnect_window_seconds'], 0)); ?> seconds. The repair command was not repeated.</p>
                <p class="quiet">The window limits retries; a check already running is allowed to finish.</p>
            <?php else: ?>
                <p class="quiet">Automatic verification: <?php echo e($verification['attempt']); ?> of <?php echo e($verification['maximum_attempts']); ?> allowed readings. The repair command was not repeated.</p>
            <?php endif; ?>
            <?php if(($verification['stop_reason'] ?? null) === 'reconnect_window_elapsed'): ?>
                <p class="reading-retry-note">The reconnect window ended before a settled result was obtained. Recovery remains unverified.</p>
            <?php endif; ?>
            <?php if(is_numeric($elapsed = $verification['elapsed_ms'] ?? null)): ?>
                <p class="quiet">Verification elapsed: <?php echo e(number_format($elapsed / 1000, 2)); ?> seconds.</p>
            <?php endif; ?>
            <?php if(is_numeric($allowance = $verification['command_timeout_seconds'] ?? null)): ?>
                <p class="quiet">Command time limit: <?php echo e(number_format($allowance, 0)); ?> seconds.</p>
            <?php endif; ?>
            <?php if(is_numeric($commandMs = $verification['command_ms'] ?? null)): ?>
                <p class="quiet">Command duration: <?php echo e(number_format($commandMs / 1000, 2)); ?> seconds.</p>
            <?php endif; ?>
        <?php endif; ?>
        <?php $__currentLoopData = ['Before' => $attempt->beforeHealthCheck, 'After' => $attempt->afterHealthCheck]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $evidence): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($stage === 'After'): ?>
                <?php echo $__env->make('incidents.verification-readings', ['recovery' => $attempt], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?>
            <?php if($evidence): ?>
                <p><b><?php echo e($stage); ?>:</b> <?php echo e($evidence->checked_at->format('g:i:s A')); ?> · <?php echo e($evidence->diagnosis_title ?: $evidence->diagnosis_code); ?> · IPv4 <?php echo e($evidence->local_ip ?: 'None'); ?> · Gateway <?php echo e($evidence->gateway_ip ?: 'None'); ?> · <?php echo e($evidence->interface_name ?: 'Unknown adapter'); ?></p>
                <?php if(is_numeric($duration = data_get($evidence->probe_meta, 'timings_ms.total'))): ?>
                    <p class="quiet">Reading duration: <?php echo e(number_format($duration / 1000, 2)); ?> seconds.</p>
                <?php endif; ?>
                <?php if(filled($windowsStatus = data_get($evidence->probe_meta, 'windows_adapter_status'))): ?>
                    <p class="quiet">Windows adapter status: <?php echo e($windowsStatus); ?></p>
                <?php endif; ?>
                <?php if(! $evidence->readingFinished()): ?>
                    <?php $__currentLoopData = (array) data_get($evidence->probe_meta, 'errors', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $readingError): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <p class="command-error">Reading error: <?php echo e($readingError); ?></p>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($attempt->error_message): ?><p class="command-error"><?php echo e($attempt->error_message); ?></p><?php endif; ?>
        <?php if($attempt->output_excerpt): ?><p class="quiet"><b>Stored command output (excerpt)</b></p><pre class="command-output"><?php echo e($attempt->output_excerpt); ?></pre><?php endif; ?>
    </details>
<?php endif; ?>
<?php if($previousAttempts->isNotEmpty()): ?>
    <details class="repair-evidence repair-history">
        <summary>Previous repair requests (<?php echo e($previousAttempts->count()); ?>)</summary>
        <?php $__currentLoopData = $previousAttempts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $previous): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="repair-request-record" data-recovery-id="<?php echo e($previous->id); ?>">
                <p class="case-when"><b><?php echo e($previous->started_at?->format('M j, g:i:s A')); ?> · <?php echo e($previous->action_label); ?></b></p>
                <p><?php echo e($previous->outcomeSummary()); ?></p>
                <p class="quiet">Command: <?php echo e($previous->execution_status === 'pending' || $previous->execution_status === null ? 'Command result not recorded' : $previous->executionLabel()); ?>. Outcome: <?php echo e($previous->verification_status === 'pending' || $previous->verification_status === null ? 'Verification result not recorded' : $previous->verificationLabel()); ?>.</p>
                <?php if($previous->command_exit_code !== null): ?><p class="quiet">Command exit code: <?php echo e($previous->command_exit_code); ?></p><?php endif; ?>
                <?php $__currentLoopData = ['Before' => $previous->beforeHealthCheck, 'After' => $previous->afterHealthCheck]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage => $evidence): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($stage === 'After'): ?>
                        <?php echo $__env->make('incidents.verification-readings', ['recovery' => $previous], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endif; ?>
                    <?php if($evidence): ?>
                        <p><b><?php echo e($stage); ?>:</b> <?php echo e($evidence->checked_at->format('M j, g:i:s A')); ?> · <?php echo e($evidence->diagnosis_title ?: $evidence->diagnosis_code); ?> · IPv4 <?php echo e($evidence->local_ip ?: 'None'); ?> · <?php echo e($evidence->interface_name ?: 'Unknown adapter'); ?></p>
                        <?php if(! $evidence->readingFinished()): ?>
                            <?php $__currentLoopData = (array) data_get($evidence->probe_meta, 'errors', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $readingError): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <p class="command-error">Reading error: <?php echo e($readingError); ?></p>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php if($previous->error_message): ?><p class="command-error"><?php echo e($previous->error_message); ?></p><?php endif; ?>
                <?php if($previous->output_excerpt): ?><p class="quiet"><b>Stored command output (excerpt)</b></p><pre class="command-output"><?php echo e($previous->output_excerpt); ?></pre><?php endif; ?>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </details>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/incidents/case.blade.php ENDPATH**/ ?>