<?php if($paginator->hasPages()): ?>
<nav class="pagination" role="navigation" aria-label="Pagination">
    <?php if($paginator->onFirstPage()): ?>
        <span class="page-link is-disabled">Previous</span>
    <?php else: ?>
        <a class="page-link" href="<?php echo e($paginator->previousPageUrl()); ?>" rel="prev">Previous</a>
    <?php endif; ?>

    <span class="page-summary">Page <?php echo e($paginator->currentPage()); ?> of <?php echo e($paginator->lastPage()); ?></span>

    <?php if($paginator->hasMorePages()): ?>
        <a class="page-link" href="<?php echo e($paginator->nextPageUrl()); ?>" rel="next">Next</a>
    <?php else: ?>
        <span class="page-link is-disabled">Next</span>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\NetRescue_CIT_Final\resources\views/partials/pagination.blade.php ENDPATH**/ ?>