@if ($paginator->hasPages())
  <nav class="pagination" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span class="page-link is-disabled" aria-disabled="true">Previous</span>
    @else
      <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <span class="page-link is-disabled">{{ $element }}</span>
      @endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span class="page-link is-current" aria-current="page">{{ $page }}</span>
          @else
            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @else
      <span class="page-link is-disabled" aria-disabled="true">Next</span>
    @endif
  </nav>
@endif
