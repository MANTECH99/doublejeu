<?php

namespace App\Http\Controllers;

use App\Models\CalendrierCreneau;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendrierController extends Controller
{
    const COULEURS = ['rouge', 'bleu', 'vert', 'jaune', 'violet'];

    const DEFAULT_COULEUR = 'rouge';

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;

        return view('jeux.calendrier.index', [
            'couple' => $couple,
            'couleurs' => self::COULEURS,
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        ActivityService::touch($request->user());

        $couple = $request->user()->coupleModel;

        $date = $request->query('date');
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            $date = now()->toDateString();
        }

        $creneaux = $couple->calendrierCreneaux()
            ->whereDate('date_jour', $date)
            ->with('user:id,name')
            ->orderBy('heure_debut')
            ->get();

        return response()->json([
            'date' => $date,
            'creneaux' => $creneaux->map(fn (CalendrierCreneau $c) => $this->serialiser($c)),
        ]);
    }

    public function creer(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        $user = $request->user();

        $request->merge([
            'heure_debut' => $this->formateHeure($request->input('heure_debut')),
            'heure_fin' => $this->formateHeure($request->input('heure_fin')),
        ]);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'titre' => ['required', 'string', 'max:255'],
            'raison' => ['nullable', 'string', 'max:255'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'couleur' => ['nullable', 'string', Rule::in(self::COULEURS)],
        ]);

        $creneau = CalendrierCreneau::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'date_jour' => $data['date'],
            'titre' => $data['titre'],
            'raison' => $data['raison'] ?? null,
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'] ?? null,
            'couleur' => $data['couleur'] ?? self::DEFAULT_COULEUR,
        ]);

        ActivityService::touch($user);

        $partner = $couple->partnerOf($user);
        if ($partner) {
            app(PushService::class)->sendToUser($partner, [
                'title' => '🗓️ Nouvelle activité au calendrier',
                'body' => $user->name.' a ajouté « '.$data['titre'].' » à '.$data['heure_debut'],
                'url' => route('calendrier.index'),
            ]);
        }

        return response()->json(['ok' => true, 'creneau' => $this->serialiser($creneau)]);
    }

    public function modifier(Request $request, CalendrierCreneau $creneau): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        abort_if($creneau->couple_id !== $couple->id, 403);

        if ($creneau->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Seul le créateur peut modifier ce créneau.'], 403);
        }

        $request->merge([
            'heure_debut' => $this->formateHeure($request->input('heure_debut')),
            'heure_fin' => $this->formateHeure($request->input('heure_fin')),
        ]);

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'raison' => ['nullable', 'string', 'max:255'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'couleur' => ['nullable', 'string', Rule::in(self::COULEURS)],
        ]);

        $creneau->forceFill([
            'titre' => $data['titre'],
            'raison' => $data['raison'] ?? null,
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'] ?? null,
            'couleur' => $data['couleur'] ?? self::DEFAULT_COULEUR,
        ])->save();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true, 'creneau' => $this->serialiser($creneau)]);
    }

    public function detruire(Request $request, CalendrierCreneau $creneau): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        abort_if($creneau->couple_id !== $couple->id, 403);

        if ($creneau->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Seul le créateur peut supprimer ce créneau.'], 403);
        }

        $creneau->delete();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true]);
    }

    protected function serialiser(CalendrierCreneau $creneau): array
    {
        return [
            'id' => $creneau->id,
            'user_id' => $creneau->user_id,
            'user_name' => $creneau->user?->name,
            'titre' => $creneau->titre,
            'raison' => $creneau->raison,
            'heure_debut' => $this->formateHeure($creneau->heure_debut),
            'heure_fin' => $this->formateHeure($creneau->heure_fin),
            'couleur' => $creneau->couleur,
        ];
    }

    private function formateHeure(?string $heure): ?string
    {
        if (! $heure) {
            return null;
        }

        return substr($heure, 0, 5);
    }
}
