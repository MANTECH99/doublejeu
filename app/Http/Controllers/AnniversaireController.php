<?php

namespace App\Http\Controllers;

use App\Models\Celebration;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AnniversaireController extends Controller
{
    /**
     * Page de préparation : on écrit un cadeau pour l'anniversaire du partenaire.
     * Le partenaire ne le découvrira que le jour de son anniversaire.
     */
    public function celebrer(Request $request): View|RedirectResponse
    {
        $me = $request->user();
        $partner = $me->coupleModel->partnerOf($me);
        $jours = $partner->joursAvantAnniversaire();

        if (! Celebration::fenetreOuverte($jours)) {
            return redirect()->route('dashboard')->with('flash', [
                'type' => 'info',
                'message' => $jours === null
                    ? $partner->name.' n\'a pas encore renseigné sa date de naissance.'
                    : 'Tu pourras préparer son anniversaire dans '.$jours.' jours.',
            ]);
        }

        return view('anniversaire.celebrer', [
            'me' => $me,
            'partner' => $partner,
            'jours' => $jours,
            'dateCelebration' => $partner->prochainAnniversaire(),
            'celebration' => Celebration::de($me, $partner),
        ]);
    }

    /**
     * Enregistre (ou met à jour) le cadeau préparé pour le partenaire.
     */
    public function enregistrer(Request $request): RedirectResponse
    {
        $me = $request->user();
        $couple = $me->coupleModel;
        $partner = $couple->partnerOf($me);
        $jours = $partner->joursAvantAnniversaire();

        abort_unless(Celebration::fenetreOuverte($jours), 403, 'La fenêtre de préparation est fermée.');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'son' => ['nullable', 'file', 'mimes:weba,webm,mp4,m4a,ogg,oga,opus,spx,wav,mp3', 'max:10240'],
            'activite' => ['nullable', 'string', 'max:255'],
            'promesse' => ['nullable', 'string', 'max:255'],
            'video' => ['nullable', 'file', 'mimes:mp4,webm,ogg,qt,mov', 'max:51200'],
            'supprimer_son' => ['nullable', 'boolean'],
            'supprimer_video' => ['nullable', 'boolean'],
            'supprimer_vocal' => ['nullable', 'boolean'],
            'vocal_path' => ['nullable', 'string', 'max:255'],
            'vocal_duration' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'vocal_bars' => ['nullable', 'string', 'max:500'],
        ]);

        $celebration = Celebration::de($me, $partner);

        $champs = [
            'message' => $data['message'],
            'activite' => $data['activite'] ?? null,
            'promesse' => $data['promesse'] ?? null,
        ];

        if ($request->hasFile('son')) {
            /** @var UploadedFile $son */
            $son = $request->file('son');
            $ancien = $celebration?->audio_path;
            $champs['audio_path'] = $son->store('anniversaire-son', 'public');

            if ($ancien) {
                Storage::disk('public')->delete($ancien);
            }
        } elseif ($request->boolean('supprimer_son') && $celebration?->audio_path) {
            Storage::disk('public')->delete($celebration->audio_path);
            $champs['audio_path'] = null;
        }

        if ($request->hasFile('video')) {
            /** @var UploadedFile $video */
            $video = $request->file('video');
            $ancien = $celebration?->video_path;
            $champs['video_path'] = $video->store('anniversaire-video', 'public');

            if ($ancien) {
                Storage::disk('public')->delete($ancien);
            }
        } elseif ($request->boolean('supprimer_video') && $celebration?->video_path) {
            Storage::disk('public')->delete($celebration->video_path);
            $champs['video_path'] = null;
        }

        // La suppression prime sur le chemin pré-rempli par le formulaire : le champ
        // caché vocal_path contient toujours le vocal existant, donc sans cette
        // priorité la case « Supprimer ce vocal » ne servirait à rien.
        if ($request->boolean('supprimer_vocal') && $celebration?->vocal_path) {
            Storage::disk('public')->delete($celebration->vocal_path);
            $champs['vocal_path'] = null;
            $champs['vocal_duration'] = null;
            $champs['vocal_bars'] = null;
        } elseif (filled($request->input('vocal_path'))) {
            $chemin = $request->input('vocal_path');

            // Le vocal a été uploadé juste avant par cet utilisateur : on refuse
            // tout chemin venu d'ailleurs (fichier d'un autre, chemin arbitraire).
            abort_unless(str_starts_with($chemin, 'anniversaire-vocal/'.$me->id.'/'), 422, 'Vocal invalide.');

            $ancien = $celebration?->vocal_path;
            $champs['vocal_path'] = $chemin;
            $champs['vocal_duration'] = $request->input('vocal_duration');
            $champs['vocal_bars'] = $request->input('vocal_bars');

            if ($ancien && $ancien !== $chemin) {
                Storage::disk('public')->delete($ancien);
            }
        }

        if ($celebration) {
            $celebration->forceFill($champs)->save();
        } else {
            Celebration::create($champs + [
                'couple_id' => $couple->id,
                'auteur_id' => $me->id,
                'destinataire_id' => $partner->id,
                'annee' => $partner->prochainAnniversaire()->year,
            ]);
        }

        ActivityService::touch($me);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Cadeau enregistré ! '.$partner->name.' ne pourra le découvrir que le '.$partner->prochainAnniversaire()->translatedFormat('j F Y').'.',
        ]);
    }

    // / Upload immédiat du vocal enregistré : la page de préparation attache
    // / ensuite le chemin renvoyé au formulaire (comme les audios de discussion).
    public function uploadVocal(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:20480', $this->regleCaptureRecorderAudio()],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('audio');
        $path = $file->store('anniversaire-vocal/'.$request->user()->id, 'public');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => parse_url(Storage::disk('public')->url($path), PHP_URL_PATH) ?: null,
        ]);
    }

    /**
     * Page de découverte : ce que le partenaire a préparé pour mon anniversaire.
     * Le cadeau reste secret jusque-là, et la page est en lecture seule.
     */
    public function ouvrir(Request $request): View|RedirectResponse
    {
        $me = $request->user();
        $partner = $me->coupleModel->partnerOf($me);
        $celebration = Celebration::revelable($partner, $me);

        if (! $celebration) {
            return redirect()->route('dashboard')->with('flash', [
                'type' => 'info',
                'message' => 'Ce cadeau s\'ouvrira le jour de ton anniversaire 🎂',
            ]);
        }

        ActivityService::touch($me);

        return view('anniversaire.cadeau', [
            'me' => $me,
            'partner' => $partner,
            'celebration' => $celebration,
        ]);
    }

    /**
     * L'utilisateur a vu les deux modals d'explication du cadeau
     * d'anniversaire : on ne les reproposera plus pour cette année.
     */
    public function infoVue(Request $request): JsonResponse
    {
        $me = $request->user();
        $partner = $me->coupleModel->partnerOf($me);
        $annee = $partner->prochainAnniversaire()?->year;

        if ($annee === null) {
            return response()->json(['error' => 'Date de naissance inconnue.'], 422);
        }

        $me->forceFill(['anniv_info_vue_annee' => $annee])->save();

        return response()->json(['ok' => true]);
    }
}
