@extends('layouts.app')

@section('title', 'Anniversaire de '.$partner->name)

@section('content')
    <div class="fadeIn">
        <div class="center">
            <div style="font-size:48px; margin-bottom:4px">🎂</div>
            <h1 class="title">L'anniversaire de {{ $partner->name }}</h1>
            <p class="subtitle">
                @if ($jours === 0)
                    C'est aujourd'hui ! 🎉
                @else
                    Dans {{ $jours }} jours
                @endif
                <span class="muted">· {{ $dateCelebration->translatedFormat('l j F Y') }}</span>
            </p>
        </div>

        <div class="card cel-secret">
            <div class="cel-secret-ico">🔒</div>
            <div class="grow">
                <strong>Tout ce que tu écris ici reste secret</strong>
                <div class="tiny muted">
                    Tu as jusqu'au {{ $dateCelebration->translatedFormat('j F Y') }} pour préparer
                    son cadeau. {{ $partner->name }} ne pourra le découvrir
                    <strong>que le jour de son anniversaire</strong> — et ne pourra rien y modifier.
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('anniversaire.enregistrer') }}" enctype="multipart/form-data" class="card"
            data-busy data-busy-label="Envoi en cours…">
            @csrf

            <label class="label" for="message">Ce que tu veux lui dire 💌</label>
            <textarea class="textarea" id="message" name="message" rows="6" maxlength="2000" required
                placeholder="Joyeux anniversaire mon amour…">{{ old('message', $celebration?->message) }}</textarea>

            <label class="label mt16" for="son">Un son pour elle/lui 🎧 <span class="muted">(facultatif)</span></label>
            <input class="input" type="file" id="son" name="son" accept="audio/*,video/mp4">
            @if ($celebration?->aUnSon())
                <audio class="cel-audio" controls preload="none" src="{{ $celebration->sonUrl() }}"></audio>
                <label class="cel-check tiny">
                    <input type="checkbox" name="supprimer_son" value="1"> Supprimer ce son
                </label>
            @endif

            <label class="label mt16" for="activite">Une activité à lui offrir 🎁 <span class="muted">(facultatif)</span></label>
            <input class="input" id="activite" name="activite" maxlength="255" value="{{ old('activite', $celebration?->activite) }}"
                placeholder="Ex : une soirée resto + un bouquet">

            <label class="label mt16" for="promesse">Une promesse à lui faire 💍 <span class="muted">(facultatif)</span></label>
            <input class="input" id="promesse" name="promesse" maxlength="255" value="{{ old('promesse', $celebration?->promesse) }}"
                placeholder="Ex : je viens diner avec toi samedi">

            <label class="label mt16" for="video">Une vidéo pour lui <span class="muted">(facultatif)</span></label>
            <input class="input" type="file" id="video" name="video" accept="video/mp4,video/webm,video/ogg,video/quicktime">
            @if ($celebration?->video_path)
                <video class="cel-audio" controls preload="none" src="{{ $celebration->videoUrl() }}"></video>
                <label class="cel-check tiny">
                    <input type="checkbox" name="supprimer_video" value="1"> Supprimer cette vidéo
                </label>
            @endif

            <button type="submit" class="btn btn-primary btn-block mt16">
                <span>{{ $celebration ? 'Mettre à jour mon cadeau' : 'Offrir ce cadeau' }}</span>
            </button>
        </form>

        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-block mt16">← Retour</a>
    </div>
@endsection
