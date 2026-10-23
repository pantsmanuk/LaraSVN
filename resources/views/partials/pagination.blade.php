<div class="pagination">
    <span>{{ $entries->total() }} {{ $itemsLabel ?? 'entries' }}@if($entries->total()) · {{ $entries->firstItem() }}–{{ $entries->lastItem() }}@endif</span>
    @if($entries->hasPages())
        <nav aria-label="Pagination">
            @if($entries->previousPageUrl())<a rel="prev" href="{{ $entries->previousPageUrl() }}">Previous</a>@endif
            <span>Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>
            @if($entries->nextPageUrl())<a rel="next" href="{{ $entries->nextPageUrl() }}">Next</a>@endif
        </nav>
    @endif
</div>
