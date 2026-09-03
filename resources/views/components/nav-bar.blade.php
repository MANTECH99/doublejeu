@php
    $route = request()->route()?->getName();
    $games = [
        ['route' => 'discussion.index', 'url' => route('discussion.index'), 'ico' => '💬', 'label' => 'Discussion'],
        ['route' => 'vo.index', 'url' => route('vo.index'), 'ico' => '🎭', 'label' => 'Vérité'],
        ['route' => 'ouinon.index', 'url' => route('ouinon.index'), 'ico' => '⚖️', 'label' => 'Oui/Non'],
        ['route' => 'mission.index', 'url' => route('mission.index'), 'ico' => '🕵️', 'label' => 'Mission'],
    ];
@endphp

<nav class="bottom-nav">
    <a href="{{ route('dashboard') }}" class="{{ $route === 'dashboard' ? 'active' : '' }}">
        <span class="ico">🏠</span>
        <span>Accueil</span>
    </a>
    @foreach ($games as $game)
        <a href="{{ $game['url'] }}" class="{{ $route === $game['route'] ? 'active' : '' }}">
            <span class="ico">{{ $game['ico'] }}@if ($game['route'] === 'discussion.index')<span class="dj-badge" id="nav-disc-badge" style="display:none"></span>@endif</span>
            <span>{{ $game['label'] }}</span>
        </a>
    @endforeach
</nav>