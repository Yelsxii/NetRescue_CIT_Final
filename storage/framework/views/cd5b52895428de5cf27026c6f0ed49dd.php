<?php
    $laterReadingFinished = (bool) $recovery->afterHealthCheck?->readingFinished();
?>
<?php $__currentLoopData = $recovery->earlierVerificationChecks(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $earlier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <article class="repair-request-record verification-reading" data-verification-check-id="<?php echo e($earlier->id); ?>">
        <p><b>Earlier automatic reading <?php echo e(data_get($earlier->probe_meta, 'recovery_verification.attempt', $index + 1)); ?>:</b> <?php echo e($earlier->checked_at->format('g:i:s A')); ?> · <?php echo e($earlier->diagnosis_title ?: $earlier->diagnosis_code); ?></p>
        <?php if(! $earlier->readingFinished() && $laterReadingFinished): ?>
            <p class="reading-retry-note">
                <?php if($recovery->verification_status === 'verified'): ?>
                    This earlier reading did not finish. A later automatic reading verified recovery; this warning does not change the verified result.
                <?php elseif($recovery->verification_status === 'observed_recovery'): ?>
                    This earlier reading did not finish. A later reading observed recovery; the repair remains unverified.
                <?php else: ?>
                    This earlier reading did not finish. A later reading completed; see the final outcome below.
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <p class="quiet">Measured adapter: <?php echo e($earlier->interface_name ?: 'Not obtained'); ?> · Requested adapter: <?php echo e(data_get($earlier->probe_meta, 'requested_interface_name') ?: 'Not recorded'); ?></p>
        <?php if(filled($windowsStatus = data_get($earlier->probe_meta, 'windows_adapter_status'))): ?>
            <p class="quiet">Windows adapter status: <?php echo e($windowsStatus); ?></p>
        <?php endif; ?>
        <?php if(is_numeric($duration = data_get($earlier->probe_meta, 'timings_ms.total'))): ?>
            <p class="quiet">Reading duration: <?php echo e(number_format($duration / 1000, 2)); ?> seconds.</p>
        <?php endif; ?>
        <?php if(is_numeric($budget = data_get($earlier->probe_meta, 'recovery_verification.configuration_timeout_seconds'))): ?>
            <p class="quiet">Configuration time limit: <?php echo e(number_format($budget, 2)); ?> seconds.</p>
        <?php endif; ?>
        <?php $__currentLoopData = (array) data_get($earlier->probe_meta, 'errors', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $readingError): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <p class="<?php echo e($laterReadingFinished ? 'quiet' : 'command-error'); ?>">Reading error: <?php echo e($readingError); ?></p>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </article>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/incidents/verification-readings.blade.php ENDPATH**/ ?>