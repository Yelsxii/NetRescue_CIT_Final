<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'size' => 20]));

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

foreach (array_filter((['name', 'size' => 20]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php ($attrs = $attributes->merge(['class' => 'icon'])); ?>
<svg <?php echo e($attrs); ?> width="<?php echo e($size); ?>" height="<?php echo e($size); ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <?php switch($name):
        case ('dashboard'): ?>
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            <?php break; ?>
        <?php case ('history'): ?>
            <path d="M12 8v5l3 2"/><path d="M3.05 11a9 9 0 1 0 .5-3.5"/><path d="M3 3v5h5"/>
            <?php break; ?>
        <?php case ('checks'): ?>
            <rect x="8" y="2" width="8" height="4" rx="1"/>
            <path d="M16 4h1.5A2.5 2.5 0 0 1 20 6.5v13A2.5 2.5 0 0 1 17.5 22h-11A2.5 2.5 0 0 1 4 19.5v-13A2.5 2.5 0 0 1 6.5 4H8"/>
            <path d="m8.5 13.2 2.2 2.2 4.6-4.8"/>
            <?php break; ?>
        <?php case ('run-check'): ?>
            <rect x="8" y="2" width="8" height="4" rx="1"/>
            <path d="M16 4h1.5A2.5 2.5 0 0 1 20 6.5v13A2.5 2.5 0 0 1 17.5 22h-11A2.5 2.5 0 0 1 4 19.5v-13A2.5 2.5 0 0 1 6.5 4H8"/>
            <path d="M10.3 10.4v6.4l5.4-3.2z" fill="currentColor" stroke="none"/>
            <?php break; ?>
        <?php case ('incident'): ?>
            <path d="M12 3 3 19h18L12 3Z"/><path d="M12 9v4M12 17h.01"/>
            <?php break; ?>
        <?php case ('computer'): ?>
            <rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/>
            <?php break; ?>
        <?php case ('plus'): ?>
            <path d="M12 5v14M5 12h14"/>
            <?php break; ?>
        <?php case ('download'): ?>
            <path d="M12 3v12m0 0 5-5m-5 5-5-5"/><path d="M5 21h14"/>
            <?php break; ?>
        <?php case ('settings'): ?>
            <path d="M4 7h16M4 12h16M4 17h16"/><circle cx="8" cy="7" r="2.2" fill="currentColor" stroke="none"/><circle cx="16" cy="12" r="2.2" fill="currentColor" stroke="none"/><circle cx="10" cy="17" r="2.2" fill="currentColor" stroke="none"/>
            <?php break; ?>
        <?php case ('online'): ?>
            <path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.2" r="1.2" fill="currentColor" stroke="none"/>
            <?php break; ?>
        <?php case ('offline'): ?>
            <path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.2" r="1.2" fill="currentColor" stroke="none"/><path d="M4 4l16 16"/>
            <?php break; ?>
        <?php case ('sun'): ?>
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            <?php break; ?>
        <?php case ('moon'): ?>
            <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5Z"/>
            <?php break; ?>
        <?php case ('activity'): ?>
            <path d="M3 12h4l2-7 4 14 2-7h6"/>
            <?php break; ?>
        <?php case ('wifi'): ?>
            <path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><path d="M12 20h.01"/>
            <?php break; ?>
        <?php case ('globe'): ?>
            <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>
            <?php break; ?>
        <?php case ('dns'): ?>
            <rect x="4" y="4" width="16" height="6" rx="2"/><rect x="4" y="14" width="16" height="6" rx="2"/><path d="M8 7h.01M8 17h.01M12 7h5M12 17h5"/>
            <?php break; ?>
        <?php case ('check'): ?>
            <path d="m5 12 4 4L19 6"/>
            <?php break; ?>
        <?php case ('alert'): ?>
            <path d="M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>
            <?php break; ?>
        <?php case ('clock'): ?>
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            <?php break; ?>
        <?php case ('wrench'): ?>
            <path d="M14.7 6.3a4 4 0 0 0-5.1 5.1L3.5 17.5a2 2 0 1 0 3 3l6.1-6.1a4 4 0 0 0 5.1-5.1l-2.6 2.6-3-3 2.6-2.6Z"/>
            <?php break; ?>
        <?php case ('chevron'): ?>
            <path d="m9 18 6-6-6-6"/>
            <?php break; ?>
        <?php case ('shield'): ?>
            <path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>
            <?php break; ?>
        <?php case ('refresh'): ?>
            <path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M18.5 9A7 7 0 0 0 6 6.5L4 12M5.5 15A7 7 0 0 0 18 17.5l2-5.5"/>
            <?php break; ?>
        <?php case ('search'): ?>
            <circle cx="11" cy="11" r="6.5"/><path d="m20 20-3.5-3.5"/>
            <?php break; ?>
        <?php default: ?>
            <circle cx="12" cy="12" r="9"/>
    <?php endswitch; ?>
</svg>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/components/icon.blade.php ENDPATH**/ ?>