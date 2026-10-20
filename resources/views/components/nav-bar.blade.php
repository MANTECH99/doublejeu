@php
    $route = request()->route()?->getName();
@endphp

<nav class="bottom-nav">
    <a href="{{ route('dashboard') }}" class="{{ $route === 'dashboard' ? 'active' : '' }}">
        <span class="ico">🏠</span>
        <span>Accueil</span>
    </a>
    <a href="{{ route('discussion.index') }}" class="{{ $route === 'discussion.index' ? 'active' : '' }}">
        <span class="ico">💬<span class="dj-badge" id="nav-disc-badge" style="display:none"></span></span>
        <span>Discussion</span>
    </a>
    <a href="{{ route('vo.index') }}" class="nb-heart{{ str_starts_with((string) $route, 'vo.') ? ' active' : '' }}" aria-label="Vérité ou Action">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.51 4.04 3 5.5l7 7Z" />
        </svg>
    </a>
    <a href="{{ route('ouinon.index') }}" class="{{ $route === 'ouinon.index' ? 'active' : '' }}">
        <span class="ico">⚖️</span>
        <span>Oui/Non</span>
    </a>
    <a href="{{ route('mission.index') }}" class="{{ $route === 'mission.index' ? 'active' : '' }}">
        <span class="ico">🕵️</span>
        <span>Mission</span>
    </a>
</nav>
