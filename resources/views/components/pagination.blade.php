@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-2 border-t border-filet px-4 py-3 text-sm no-print" aria-label="Pagination">
        <span class="text-encre-3">{{ $paginator->firstItem() }} à {{ $paginator->lastItem() }} sur {{ $paginator->total() }}</span>
        <span class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondaire btn-petit opacity-50" aria-disabled="true">Précédent</span>
            @else
                <a class="btn btn-secondaire btn-petit" href="{{ $paginator->previousPageUrl() }}" rel="prev">Précédent</a>
            @endif
            <span class="px-2 text-encre-2">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a class="btn btn-secondaire btn-petit" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant</a>
            @else
                <span class="btn btn-secondaire btn-petit opacity-50" aria-disabled="true">Suivant</span>
            @endif
        </span>
    </nav>
@endif
