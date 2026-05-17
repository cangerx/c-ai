@if ($paginator->hasPages())
<nav role="navigation" aria-label="分页" style="display:flex; align-items:center; justify-content:center; gap:8px; margin-top:20px;">
    @if ($paginator->onFirstPage())
        <span style="display:inline-flex; align-items:center; padding:6px 14px; font-size:13px; border-radius:8px; color:var(--text-muted,#a1a1aa); border:1px solid var(--line,rgba(0,0,0,0.06)); opacity:0.5; cursor:not-allowed;">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;"><path d="M15 19l-7-7 7-7"/></svg>上一页
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="display:inline-flex; align-items:center; padding:6px 14px; font-size:13px; border-radius:8px; color:var(--text-secondary,#71717a); border:1px solid var(--line,rgba(0,0,0,0.06)); text-decoration:none; transition:all 0.15s;">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;"><path d="M15 19l-7-7 7-7"/></svg>上一页
        </a>
    @endif

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="display:inline-flex; align-items:center; padding:6px 14px; font-size:13px; border-radius:8px; color:var(--text-secondary,#71717a); border:1px solid var(--line,rgba(0,0,0,0.06)); text-decoration:none; transition:all 0.15s;">
            下一页<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-left:4px;"><path d="M9 5l7 7-7 7"/></svg>
        </a>
    @else
        <span style="display:inline-flex; align-items:center; padding:6px 14px; font-size:13px; border-radius:8px; color:var(--text-muted,#a1a1aa); border:1px solid var(--line,rgba(0,0,0,0.06)); opacity:0.5; cursor:not-allowed;">
            下一页<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-left:4px;"><path d="M9 5l7 7-7 7"/></svg>
        </span>
    @endif
</nav>
@endif
