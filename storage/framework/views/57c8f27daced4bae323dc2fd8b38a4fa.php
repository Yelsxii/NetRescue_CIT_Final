<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status' => 'neutral', 'label' => null]));

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

foreach (array_filter((['status' => 'neutral', 'label' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $normalized = strtolower((string) $status);
    $class = match ($normalized) {
        'healthy', 'resolved', 'success', 'none' => 'pill pill-success',
        'degraded', 'warning', 'moderate', 'low' => 'pill pill-warning',
        'critical', 'danger', 'high', 'active', 'offline' => 'pill pill-danger',
        default => 'pill pill-neutral',
    };
?>
<span <?php echo e($attributes->merge(['class' => $class])); ?>><span class="pill-dot"></span><?php echo e($label ?? ucfirst($normalized)); ?></span>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/components/status-pill.blade.php ENDPATH**/ ?>