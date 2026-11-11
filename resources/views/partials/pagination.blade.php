@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Navigasi halaman">
        <p class="pagination__info">
            @if (method_exists($paginator, 'total'))
                Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
            @else
                Halaman {{ $paginator->currentPage() }}
            @endif
        </p>
        <ul>
            <li>
                @if ($paginator->onFirstPage())
                    <span class="btn btn--secondary btn--sm is-disabled" aria-disabled="true">‹ Sebelumnya</span>
                @else
                    <a class="btn btn--secondary btn--sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Sebelumnya</a>
                @endif
            </li>
            @isset($elements)
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="pagination__gap">…</span></li>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span class="btn btn--primary btn--sm" aria-current="page">{{ $page }}</span>
                                @else
                                    <a class="btn btn--ghost btn--sm" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            @endisset
            <li>
                @if ($paginator->hasMorePages())
                    <a class="btn btn--secondary btn--sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya ›</a>
                @else
                    <span class="btn btn--secondary btn--sm is-disabled" aria-disabled="true">Berikutnya ›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
