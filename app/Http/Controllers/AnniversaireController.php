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
            'son' => ['nullable', 'file', 'mimes:webm,mp4,ogg,oga,m4a,mp3,wav', 'max:10240'],
            'activite' => ['nullable', 'string', 'max:255'],
            'promesse' => ['nullable', 'string', 'max:255'],
            'video' => ['nullable', 'file', 'mimes:mp4,webm,ogg,qt,mov', 'max:51200'],
            'supprimer_son' => ['nullable', 'boolean'],
            'supprimer_video' => ['nullable', 'boolean'],
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
