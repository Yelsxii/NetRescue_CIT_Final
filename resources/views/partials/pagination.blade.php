@if ($paginator->hasPages())
<nav class="pagination" role="navigation" aria-label="Pagination">
    @if ($paginator->onFirstPage())
        <span class="page-link is-disabled">Previous</span>
    @else
        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif

    <span class="page-summary">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

    @if ($paginator->hasMorePages())
        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @else
        <span class="page-link is-disabled">Next</span>
    @endif
</nav>
@endif
