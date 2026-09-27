@props(['url'])

{{-- Pavé numérique : ne s'affiche que sur un appareil de confiance (jeton connu). --}}
<div class="pin" data-pin data-pin-url="{{ $url }}" data-pin-device-url="{{ route('pin.login.device') }}" hidden>
    <p class="pin-compte" data-pin-compte hidden></p>

    <div class="pin-points" data-pin-points role="status" aria-label="Code PIN à saisir">
        <i></i><i></i><i></i><i></i><i></i><i></i>
    </div>

    <div class="pin-pave">
        @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $chiffre)
            <button type="button" class="pin-key" data-pin-key="{{ $chiffre }}">{{ $chiffre }}</button>
        @endforeach
        <button type="button" class="pin-key pin-key-mu" data-pin-key="clear" aria-label="Effacer">⌫</button>
        <button type="button" class="pin-key" data-pin-key="0">0</button>
        <button type="button" class="pin-key pin-key-ok" data-pin-key="ok" aria-label="Valider">→</button>
    </div>

    <p class="err center" data-pin-err hidden style="margin:14px 0 0"></p>
</div>
