<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 px-4 py-3 border-top" style="background: #eff4ff;">
    <span class="small text-secondary" data-entries-count>
        Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} {{ $paginator->total() === 1 ? 'entry' : 'entries' }}
    </span>

    @if ($paginator->hasPages())
        <nav aria-label="{{ $label }} pagination">
            <ul class="pagination pagination-sm mb-0 flex-wrap">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">Previous</span></li>
                @else
                    <li class="page-item"><a class="page-link portal-ajax-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous {{ $label }} page">Previous</a></li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @elseif (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link portal-ajax-link" href="{{ $url }}" aria-label="Go to {{ $label }} page {{ $page }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <li class="page-item"><a class="page-link portal-ajax-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next {{ $label }} page">Next</a></li>
                @else
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">Next</span></li>
                @endif
            </ul>
        </nav>
    @endif
</div>
