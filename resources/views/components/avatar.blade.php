@props(['user' => null, 'name' => null, 'color' => null, 'style' => ''])
@php
    $u = $user;
    $n = $name ?? ($u?->name ?? '');
    $c = $color ?? ($u?->avatarColor() ?? '#E63946');
    $init = mb_strtoupper(mb_substr($n, 0, 1));
    $photo = $u?->hasPhoto() ? $u->photoUrl() : null;
@endphp
@if ($photo)
<div {{ $attributes->merge(['class' => 'avatar', 'style' => "background-image:url('{$photo}'); background-size:cover; background-position:center 30%;{$style}"]) }}></div>
@else
<div {{ $attributes->merge(['class' => 'avatar', 'style' => "background:{$c}{$style}"]) }}>
    {{ $init }}
</div>
@endif
