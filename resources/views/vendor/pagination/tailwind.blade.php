@if ($paginator->hasPages())
<nav role="navigation" aria-label="分页" style="display:flex; align-items:center; justify-content:center; gap:6px; margin-top:20px; flex-wrap:wrap;">
    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; color:var(--text-muted,#a1a1aa); opacity:0.4; cursor:not-allowed;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; color:var(--text-secondary,#71717a); border:1px solid var(--line,rgba(0,0,0,0.06)); text-decoration:none; transition:all 0.15s;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
        </a>
    @endif

    {{-- Pages --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span style="display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 4px; font-size:13px; color:var(--text-muted,#a1a1aa);">{{ $element }}</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span style="display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 8px; font-size:13px; font-weight:600; border-radius:8px; background:var(--accent,#2d5bf0); color:#fff;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 8px; font-size:13px; font-weight:500; border-radius:8px; color:var(--text-secondary,#71717a); border:1px solid var(--line,rgba(0,0,0,0.06)); text-decoration:none; transition:all 0.15s;">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; color:var(--text-secondary,#71717a); border:1px solid var(--line,rgba(0,0,0,0.06)); text-decoration:none; transition:all 0.15s;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
        </a>
    @else
        <span style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; color:var(--text-muted,#a1a1aa); opacity:0.4; cursor:not-allowed;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
        </span>
    @endif
</nav>
@endif
