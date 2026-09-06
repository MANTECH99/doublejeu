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
                'proposition_user1' => [],
                'proposition_user2' => [],
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

    /**
     * Reçoit une lettre en cours de saisie (brouillon) et vérifie les mots complets.
     *
     * Le solveur tape librement ses lettres (même fausses : le créateur les observe en
     * temps réel via `etat`). Dès qu'un mot est entièrement couvert (brouillon + cases
     * validées), il est vérifié en entier : s'il est bon, ses cases se verrouillent ;
     * sinon les lettres restent visibles, modifiables.
     */
    public function verifier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'r' => ['required', 'integer'],
            'c' => ['required', 'integer'],
            'lettre' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $couple = $user->coupleModel;
        $partenaire = $couple->partnerOf($user);

        // La seule grille que je peux remplir est celle créée par mon/ma partenaire.
        $grille = GrilleMotsCroises::pourCreateur($couple, $partenaire->id);
        if ($grille === null || ! $grille->grille) {
            return response()->json(['error' => 'Ta grille n\'a pas encore été créée.'], 422);
        }

        $estUser1 = $couple->user1_id === $user->id;
        $col = $estUser1 ? 'reponses_user1' : 'reponses_user2';
        $brouillonCol = $estUser1 ? 'proposition_user1' : 'proposition_user2';
        $attCol = $estUser1 ? 'attribues_user1' : 'attribues_user2';
        $attColLui = $estUser1 ? 'attribues_user2' : 'attribues_user1';

        $cases = $grille->grille['cases'];
        $cle = (int) $data['r'].','.(int) $data['c'];

        // Case noire ou hors grille : rien à faire.
        if (($cases[$cle] ?? '') === '') {
            return response()->json(['ok' => true]);
        }

        $reponses = $grille->{$col} ?? [];
        $brouillon = $grille->{$brouillonCol} ?? [];

        // Déjà validée (via un autre mot) : immuable.
        if (($reponses[$cle] ?? '') !== '') {
            return response()->json(['ok' => true]);
        }

        $lettre = self::normaliseLettre((string) ($data['lettre'] ?? ''));
        if ($lettre !== '' && ! preg_match('/^[A-Z]$/', $lettre)) {
            $lettre = '';
        }

        if ($lettre === '') {
            unset($brouillon[$cle]);
        } else {
            $brouillon[$cle] = $lettre;
        }

        $attribues = array_values(array_unique(array_merge(
            $grille->attribues_user1 ?? [],
            $grille->attribues_user2 ?? []
        )));
        $attribuesMoi = array_values($grille->attribuesPour($user->id));
        $attribuesLui = array_values($grille->attribuesPour($partenaire->id));

        $statuts = [];
        $gagne = 0;

        foreach (($grille->grille['mots'] ?? []) as $i => $mot) {
            $cles = $this->casesDuMot($mot);

            // Mot déjà entièrement verrouillé.
            $dejaTrouve = true;
            foreach ($cles as $kk) {
                if (($reponses[$kk] ?? '') === '') {
                    $dejaTrouve = false;
                    break;
                }
            }
            if ($dejaTrouve) {
                continue;
            }

            // Le mot est « complet » quand chaque case est couverte (brouillon ou validée).
            $effectives = [];
            $complet = true;
            foreach ($cles as $kk) {
                $lettreEff = ($reponses[$kk] ?? '') !== '' ? $reponses[$kk] : ($brouillon[$kk] ?? '');
                $effectives[$kk] = $lettreEff;
                if ($lettreEff === '') {
                    $complet = false;
                    break;
                }
            }
            if (! $complet) {
                continue;
            }

            $juste = true;
            foreach ($effectives as $kk => $lettreEff) {
                if (self::normaliseLettre($lettreEff) !== self::normaliseLettre($cases[$kk])) {
                    $juste = false;
                    break;
                }
            }

            if (! $juste) {
                // Tentative fausse : on garde les lettres, le créateur voit l'erreur.
                $statuts[$i] = ['statut' => 'incorrect', 'cases' => array_keys($effectives)];

                continue;
            }

            $statuts[$i] = ['statut' => 'correct', 'cases' => array_keys($effectives)];
            foreach ($effectives as $kk => $lettreEff) {
                unset($brouillon[$kk]);
                if (($reponses[$kk] ?? '') !== '') {
                    continue;
                }
                $reponses[$kk] = mb_strtoupper($lettreEff);
                if (! in_array($kk, $attribues, true) && ! in_array($kk, $attribuesMoi, true)) {
                    $attribuesMoi[] = $kk;
                    $gagne++;
                }
            }
        }

        $grille->forceFill([
            $col => $reponses,
            $brouillonCol => $brouillon,
            $attCol => $attribuesMoi,
            $attColLui => $attribuesLui,
        ])->save();

        if ($gagne > 0) {
            for ($n = 0; $n < $gagne; $n++) {
                Point::add($user, $couple, 1, 'Mot trouvé dans les Mots Croisés', 'mots_croises');
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
            'statuts' => $statuts,
            'complete' => $complete,
            'etat' => $this->etatPour($grille, $user),
        ]);
    }

    // ---- Vue d'une grille du point de vue du solveur (partagée avec le créateur pour l'observation) ----

    private function etatPour(GrilleMotsCroises $grille, $solveur): array
    {
        $couple = $grille->couple;
        $col = $couple->user1_id === $solveur->id ? 'reponses_user1' : 'reponses_user2';
        $brouillonCol = $couple->user1_id === $solveur->id ? 'proposition_user1' : 'proposition_user2';
        $reponses = $grille->{$col} ?? [];
        $brouillon = $grille->{$brouillonCol} ?? [];
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
            'brouillon' => array_intersect_key($brouillon, $cases),
            'noires' => $noires,
            'numeros' => $numeros,
            'mots' => $mots,
            'progress' => ['trouvees' => $comptees, 'total' => $total],
            'feedback' => $this->feedbackPour($grilleArray, $reponses, $brouillon),
            'complete' => $grille->estComplete(),
        ];
    }

    /**
     * Feedback type Mastermind : nombre de lettres correctes (présentes dans leur
     * mot) et bien placées (à la bonne position), sans jamais révéler lesquelles.
     */
    private function feedbackPour(array $grilleArray, array $reponses, array $brouillon): array
    {
        $cases = $grilleArray['cases'] ?? [];
        $correctes = 0;
        $bienPlacees = 0;

        foreach (($grilleArray['mots'] ?? []) as $mot) {
            $sol = [];
            $eff = [];
            foreach ($this->casesDuMot($mot) as $kk) {
                if (($cases[$kk] ?? '') === '') {
                    continue;
                }
                $sol[] = self::normaliseLettre($cases[$kk]);
                $lettre = ($reponses[$kk] ?? '') !== '' ? $reponses[$kk] : ($brouillon[$kk] ?? '');
                $eff[] = self::normaliseLettre((string) $lettre);
            }

            for ($i = 0, $n = count($sol); $i < $n; $i++) {
                if ($eff[$i] !== '' && $eff[$i] === $sol[$i]) {
                    $bienPlacees++;
                }
            }

            $freqEff = array_count_values(array_filter($eff, fn ($l) => $l !== ''));
            $freqSol = array_count_values($sol);
            foreach ($freqEff as $lettre => $quantite) {
                $correctes += min($quantite, $freqSol[$lettre] ?? 0);
            }
        }

        return ['correctes' => $correctes, 'bien_placees' => $bienPlacees];
    }

    /** Clés des cases (r,c) couvertes par un mot de la grille. */
    private function casesDuMot(array $mot): array
    {
        $long = $mot['taille'] ?? mb_strlen((string) ($mot['mot'] ?? ''));
        [$r, $c] = $mot['position'];
        $out = [];
        for ($k = 0; $k < $long; $k++) {
            $out[] = $mot['orientation'] === 'h' ? "{$r},".($c + $k) : ($r + $k).",{$c}";
        }

        return $out;
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
