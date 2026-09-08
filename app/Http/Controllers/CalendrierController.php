<?php

namespace App\Http\Controllers;

use App\Models\CalendrierCreneau;
use App\Services\ActivityService;
use App\Services\PushService;
use Carbon\Carbon;
use DateTimeZone;
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
        $tz = $this->tzDepuis($request);

        $date = $request->query('date');
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            $date = now()->setTimezone($tz)->toDateString();
        }

        // Bornes du jour demandé (fuseau du lecteur) converties en UTC : un
        // créneau appartient au jour selon l'heure de CELUI QUI REGARDE.
        $jourDebut = Carbon::parse($date, $tz)->startOfDay()->utc();
        $jourFin = $jourDebut->copy()->addDay();

        $creneaux = $couple->calendrierCreneaux()
            ->where(function ($q) use ($jourDebut, $jourFin, $date) {
                $q->whereBetween('debut_utc', [$jourDebut, $jourFin])
                    // Créneaux historiques sans franc-temporel : on retombe sur
                    // le jour saisi à l'époque (rétrocompatibilité).
                    ->orWhere(function ($q2) use ($date) {
                        $q2->whereNull('debut_utc')->whereDate('date_jour', $date);
                    });
            })
            ->with('user:id,name')
            ->orderBy('debut_utc')
            ->get();

        return response()->json([
            'date' => $date,
            'creneaux' => $creneaux->map(fn (CalendrierCreneau $c) => $this->serialiser($c, $tz)),
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
            'timezone' => ['nullable', 'string', 'max:64', Rule::in(DateTimeZone::listIdentifiers())],
        ]);

        $tz = $data['timezone'] ?? config('app.timezone');

        $creneau = CalendrierCreneau::create([
            'couple_id' => $couple->id,
            'user_id' => $user->id,
            'date_jour' => $data['date'],
            'titre' => $data['titre'],
            'raison' => $data['raison'] ?? null,
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'] ?? null,
            'debut_utc' => Carbon::parse($data['date'].' '.$data['heure_debut'], $tz)->utc(),
            'fin_utc' => $data['heure_fin']
                ? Carbon::parse($data['date'].' '.$data['heure_fin'], $tz)->utc()
                : null,
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

        return response()->json(['ok' => true, 'creneau' => $this->serialiser($creneau, $tz)]);
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
            'timezone' => ['nullable', 'string', 'max:64', Rule::in(DateTimeZone::listIdentifiers())],
        ]);

        $tz = $data['timezone'] ?? config('app.timezone');

        $creneau->forceFill([
            'titre' => $data['titre'],
            'raison' => $data['raison'] ?? null,
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'] ?? null,
            'debut_utc' => Carbon::parse($creneau->date_jour.' '.$data['heure_debut'], $tz)->utc(),
            'fin_utc' => $data['heure_fin']
                ? Carbon::parse($creneau->date_jour.' '.$data['heure_fin'], $tz)->utc()
                : null,
            'couleur' => $data['couleur'] ?? self::DEFAULT_COULEUR,
        ])->save();

        ActivityService::touch($request->user());

        return response()->json(['ok' => true, 'creneau' => $this->serialiser($creneau, $tz)]);
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

    protected function serialiser(CalendrierCreneau $creneau, string $tz): array
    {
        // L'heure affichée est celle de CELUI QUI REGARDE : on convertit
        // l'instant stocké (UTC) vers son fuseau. Créneaux historiques sans
        // instant : on interprète la valeur saisie dans son fuseau (best-effort).
        $debut = $creneau->debut_utc
            ? $creneau->debut_utc->copy()->setTimezone($tz)
            : ($creneau->heure_debut ? Carbon::parse($creneau->date_jour.' '.$creneau->heure_debut, $tz) : null);
        $fin = $creneau->fin_utc
            ? $creneau->fin_utc->copy()->setTimezone($tz)
            : ($creneau->heure_fin ? Carbon::parse($creneau->date_jour.' '.$creneau->heure_fin, $tz) : null);

        return [
            'id' => $creneau->id,
            'user_id' => $creneau->user_id,
            'user_name' => $creneau->user?->name,
            'titre' => $creneau->titre,
            'raison' => $creneau->raison,
            'heure_debut' => $debut?->format('H:i'),
            'heure_fin' => $fin?->format('H:i'),
            'couleur' => $creneau->couleur,
        ];
    }

    // Résout le fuseau horaire fourni par le client (nom IANA) ; sinon le
    // fuseau applicatif. La lecture ignore un fuseau invalide (défense en profondeur).
    private function tzDepuis(Request $request): string
    {
        $tz = $request->query('tz') ?? $request->input('timezone');
        if (is_string($tz) && in_array($tz, DateTimeZone::listIdentifiers(), true)) {
            return $tz;
        }

        return config('app.timezone');
    }

    // Normalise une heure (ex. "18:30:00" → "18:30") avant validation.
    private function formateHeure(?string $heure): ?string
    {
        if (! $heure) {
            return null;
        }

        return substr($heure, 0, 5);
    }
}
