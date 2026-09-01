<?php

namespace App\Http\Controllers;

use App\Models\GrilleMotsCroises;
use App\Models\MotCroiseContenu;
use App\Models\Point;
use App\Services\ActivityService;
use App\Services\MotsCroisesGenerator;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MotsCroisesController extends Controller
{
    public const LONGUEUR_MIN = 4;

    public const LONGUEUR_MAX = 10;

    public const MIN_MOTS_GRILLE = 3;

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        return view('jeux.mots-croises.index', [
            'couple' => Auth::user()->coupleModel,
        ]);
    }

    // ---- Gestion des mots personnels (privés : chacun crée les siens) ----

    public function mots(): View
    {
        ActivityService::touch(Auth::user());

        return view('jeux.mots-croises.mots', [
            'mesMots' => MotCroiseContenu::where('created_by', Auth::id())->orderByDesc('id')->get(),
            'couple' => Auth::user()->coupleModel,
        ]);
    }

    public function creerMot(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mot' => ['required', 'string', 'max:'.self::LONGUEUR_MAX],
            'indice' => ['required', 'string', 'max:200'],
        ]);

        $normalise = self::normaliserMot($data['mot']);
        if (mb_strlen($normalise) < self::LONGUEUR_MIN || mb_strlen($normalise) > self::LONGUEUR_MAX) {
            return back()->withErrors(['mot' => 'Un mot de '.self::LONGUEUR_MIN.' à '.self::LONGUEUR_MAX.' lettres (sans accents ni espaces).']);
        }

        $deja = MotCroiseContenu::where('mot', $normalise)
            ->where('created_by', $request->user()->id)
            ->exists();
        if ($deja) {
            return back()->withErrors(['mot' => "\"$normalise\" est déjà dans ta liste."]);
        }

        MotCroiseContenu::create([
            'mot' => $normalise,
            'indice' => trim($data['indice']),
            'created_by' => $request->user()->id,
        ]);

        $partenaire = $request->user()->coupleModel->partnerOf($request->user());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Mot ajouté ! Tu pourras générer une grille rien que pour '.$partenaire->name.'.',
        ]);
    }

    public function detruireMot(Request $request, int $id): RedirectResponse
    {
        $mot = MotCroiseContenu::find($id);
        if ($mot && $mot->created_by === $request->user()->id) {
            $mot->delete();

            return back()->with('flash', ['type' => 'success', 'message' => 'Mot supprimé.']);
        }

        return back()->with('flash', ['type' => 'error', 'message' => 'Suppression impossible.']);
    }

    // ---- Génération : je crée LA grille de mon/ma partenaire (avec mes mots) ----

    public function generer(Request $request): JsonResponse
    {
        $user = $request->user();
        $couple = $user->coupleModel;
        $partenaire = $couple->partnerOf($user);

        $mots = MotCroiseContenu::where('created_by', $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => ['mot' => $m->mot, 'indice' => $m->indice])
            ->values()
            ->all();

        if (count($mots) < self::MIN_MOTS_GRILLE) {
            return response()->json([
                'error' => 'Ajoute d\'abord au moins '.self::MIN_MOTS_GRILLE.' mots dans « Mes mots » pour générer une grille.',
            ], 422);
        }

        $generee = MotsCroisesGenerator::generer($mots);
        if ($generee === null) {
            return response()->json([
                'error' => 'Impossible de croiser ces mots. Essaie avec des mots plus variés (longueurs différentes).',
            ], 422);
        }

        $existante = GrilleMotsCroises::pourCreateur($couple, $user->id);
        if ($existante) {
            $existante->forceFill([
                'statut' => 'en_cours',
                'mots' => $generee['mots'],
                'grille' => $generee,
                'reponses_user1' => [],
                'reponses_user2' => [],
                'attribues_user1' => [],
                'attribues_user2' => [],
            ])->save();
            $grille = $existante;
        } else {
            $grille = GrilleMotsCroises::create([
                'couple_id' => $couple->id,
                'createur_id' => $user->id,
                'semaine' => now()->startOfWeek()->toDateString(),
                'statut' => 'en_cours',
                'mots' => $generee['mots'],
                'grille' => $generee,
            ]);
        }

        // Le créateur observe l'autre remplir la grille en temps réel.
        return response()->json([
            'ok' => true,
            'etat' => $this->etatPour($grille, $partenaire),
        ]);
    }

    // ---- État : ma grille (créée par l'autre, à remplir) + ma grille pour l'autre (à observer) ----

    public function state(Request $request): JsonResponse
    {
        $user = $request->user();
        $couple = $user->coupleModel;
        $partenaire = $couple->partnerOf($user);

        return response()->json([
            'partenaire' => $partenaire->name,
            'mesMots' => MotCroiseContenu::where('created_by', $user->id)->count(),
            'aGrillePourMoi' => ($g = GrilleMotsCroises::pourCreateur($couple, $partenaire->id))
                ? $this->etatPour($g, $user)
                : null,
            'maGrillePourX' => ($g = GrilleMotsCroises::pourCreateur($couple, $user->id))
                ? $this->etatPour($g, $partenaire)
                : null,
        ]);
    }

    // ---- Remplissage : seulement le/la partenaire résout la grille créée par l'autre ----

    public function verifier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lettres' => ['required', 'array'],
            'lettres.*' => ['required', 'string', 'size:1'],
        ], [
            'lettres.required' => 'Aucune lettre à vérifier.',
        ]);

        $user = $request->user();
        $couple = $user->coupleModel;
        $partenaire = $couple->partnerOf($user);

        // La seule grille que je peux remplir est celle créée par mon/ma partenaire.
        $grille = GrilleMotsCroises::pourCreateur($couple, $partenaire->id);
        if ($grille === null || ! $grille->grille) {
            return response()->json(['error' => 'Ta grille n\'a pas encore été créée.'], 422);
        }

        $col = $couple->user1_id === $user->id ? 'reponses_user1' : 'reponses_user2';
        $attribues = array_values(array_unique(array_merge(
            $grille->attribues_user1 ?? [],
            $grille->attribues_user2 ?? []
        )));
        $attribuesMoi = $grille->attribuesPour($user->id);
        $attribuesLui = $grille->attribuesPour($partenaire->id);

        $reponses = $grille->{$col} ?? [];
        $cases = $grille->grille['cases'];
        $resultat = [];
        $gagne = 0;

        foreach ($data['lettres'] as $cle => $lettre) {
            if (! array_key_exists($cle, $cases)) {
                continue;
            }
            $solution = $cases[$cle];
            if ($solution === '') {
                continue;
            }

            // Déjà en place : rien à faire.
            if (isset($reponses[$cle]) && $reponses[$cle] !== '') {
                $resultat[$cle] = ['statut' => 'deja', 'lettre' => $reponses[$cle]];

                continue;
            }

            if (self::normaliseLettre($lettre) === self::normaliseLettre($solution)) {
                $dejaAttribuee = in_array($cle, $attribues, true);
                $nouveauPourMoi = ! in_array($cle, $attribuesMoi, true);

                if (! $dejaAttribuee && $nouveauPourMoi) {
                    $attribuesMoi[] = $cle;
                    $gagne++;
                }
                $reponses[$cle] = mb_strtoupper($solution);
                $resultat[$cle] = ['statut' => 'correct', 'lettre' => mb_strtoupper($solution)];
            } else {
                $resultat[$cle] = ['statut' => 'incorrect'];
            }
        }

        $attCol = $couple->user1_id === $user->id ? 'attribues_user1' : 'attribues_user2';
        $attColLui = $couple->user1_id === $user->id ? 'attribues_user2' : 'attribues_user1';

        $grille->forceFill([
            $col => $reponses,
            $attCol => $attribuesMoi,
            $attColLui => $attribuesLui,
        ])->save();

        if ($gagne > 0) {
            for ($n = 0; $n < $gagne; $n++) {
                Point::add($user, $couple, 1, 'Lettre trouvée dans les Mots Croisés', 'mots_croises');
            }
        }

        $complete = $grille->estComplete();
        if ($complete && $grille->statut === 'en_cours') {
            $grille->forceFill(['statut' => 'terminee'])->save();
            foreach ($couple->users as $u) {
                app(PushService::class)->sendToUser($u, [
                    'title' => '🧩 Mots croisés complétés !',
                    'body' => 'La grille inventée par '.$grille->createur?->name.' est terminée. Bravo !',
                    'url' => route('mots-croises.index'),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'points_gagnes' => $gagne,
            'resultat' => $resultat,
            'complete' => $complete,
            'etat' => $this->etatPour($grille, $user),
        ]);
    }

    // ---- Vue d'une grille du point de vue du solveur (partagée avec le créateur pour l'observation) ----

    private function etatPour(GrilleMotsCroises $grille, $solveur): array
    {
        $couple = $grille->couple;
        $col = $couple->user1_id === $solveur->id ? 'reponses_user1' : 'reponses_user2';
        $reponses = $grille->{$col} ?? [];
        $grilleArray = $grille->grille;

        $cases = [];
        $noires = [];
        foreach (($grilleArray['cases'] ?? []) as $cle => $lettre) {
            if ($lettre === '') {
                $noires[] = $cle;

                continue;
            }
            $cases[$cle] = $reponses[$cle] ?? '';
        }

        $numeros = $this->numerosPour($grilleArray, $noires);

        $mots = array_map(function ($m) use ($numeros) {
            [$r, $c] = $m['position'];

            return [
                'numero' => $numeros["{$r},{$c}"] ?? $m['numero'],
                'indice' => $m['indice'],
                'orientation' => $m['orientation'],
                'position' => $m['position'],
                'taille' => mb_strlen($m['mot']),
            ];
        }, $grille->mots ?? []);

        $comptees = count(array_filter($cases, fn ($v) => $v !== ''));
        $total = count(array_filter($grilleArray['cases'] ?? [], fn ($v) => $v !== ''));

        return [
            'createur' => $grille->createur?->name,
            'statut' => $grille->statut,
            'lignes' => $grilleArray['lignes'],
            'colonnes' => $grilleArray['colonnes'],
            'cases' => $cases,
            'noires' => $noires,
            'numeros' => $numeros,
            'mots' => $mots,
            'progress' => ['trouvees' => $comptees, 'total' => $total],
            'complete' => $grille->estComplete(),
        ];
    }

    /** Numérotation standard des cases de départ de mots (ordre de lecture). */
    private function numerosPour(array $grilleArray, array $noires): array
    {
        $noiresSet = array_flip($noires);
        $numeros = [];
        $compteur = 1;
        $lignes = $grilleArray['lignes'];
        $colonnes = $grilleArray['colonnes'];

        for ($r = 0; $r < $lignes; $r++) {
            for ($c = 0; $c < $colonnes; $c++) {
                $cle = "{$r},{$c}";
                if (isset($noiresSet[$cle])) {
                    continue;
                }

                $estDebutH = ($c === 0 || isset($noiresSet["{$r},".($c - 1)])) && $c + 1 < $colonnes && ! isset($noiresSet["{$r},".($c + 1)]);
                $estDebutV = ($r === 0 || isset($noiresSet[($r - 1).",{$c}"])) && $r + 1 < $lignes && ! isset($noiresSet[($r + 1).",{$c}"]);

                if ($estDebutH || $estDebutV) {
                    $numeros[$cle] = $compteur++;
                }
            }
        }

        return $numeros;
    }

    public static function normaliseLettre(string $lettre): string
    {
        return strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtoupper($lettre)));
    }

    public static function normaliserMot(string $mot): string
    {
        $mot = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $mot));
        $mot = preg_replace('/[^A-Z]/', '', $mot) ?? '';

        return $mot;
    }
}
