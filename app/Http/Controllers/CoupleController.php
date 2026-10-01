<?php

namespace App\Http\Controllers;

use App\Models\Celebration;
use App\Models\Couple;
use App\Models\MeteoCouple;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\PushService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CoupleController extends Controller
{
    public function dashboard(): View
    {
        ActivityService::touch(Auth::user());

        $couple = Auth::user()->coupleModel;
        $partner = $couple->partnerOf(Auth::user());

        $meteo = MeteoCouple::aujourdhuiPour($couple);
        $maHumeur = $meteo?->humeurActuellePour(Auth::user()->id);
        $saHumeur = $meteo?->humeurActuellePour($partner->id);

        $meteoInfo = function (?string $humeur): ?array {
            if (! $humeur) {
                return null;
            }

            return [
                'valeur' => $humeur,
                'label' => MeteoCouple::METEOS[$humeur]['label'],
                'emoji' => MeteoCouple::METEOS[$humeur]['emoji'],
                'lottie' => MeteoCouple::METEOS[$humeur]['lottie'],
            ];
        };

        // Chaque ligne d'anniversaire : le compte à rebours, le cadeau à préparer
        // (côté partenaire) et le cadeau qui s'ouvre le jour J (côté moi).
        $anniversaire = function (User $qui, User $auteur, bool $estMoi): array {
            $celebration = $estMoi
                ? Celebration::revelable($auteur, $qui)
                : Celebration::de($auteur, $qui);

            return [
                'name' => $qui->name,
                'date' => $qui->prochainAnniversaire(),
                'jours' => $qui->joursAvantAnniversaire(),
                'celebration' => $celebration,
                'peutCelebrer' => ! $estMoi && Celebration::fenetreOuverte($qui->joursAvantAnniversaire()),
                'cadeauDuJour' => $estMoi && $qui->joursAvantAnniversaire() === 0 && $celebration !== null,
                // Le tutoriel s'affiche à l'ouverture de la fenêtre, une fois par
                // utilisateur et par anniversaire : on ne le repropose pas ensuite.
                'fenetreJours' => Celebration::FENETRE_JOURS,
                // Pas de condition sur le cadeau : le bouton Célébrer du hero est le seul
                // moyen d'atteindre la page cadeau, donc le tutoriel est de
                // toute façon passé avant. On ne le masque donc jamais, et il
                // revient tant que l'utilisateur ne l'a pas validé.
                'infoDue' => ! $estMoi
                    && Celebration::fenetreOuverte($qui->joursAvantAnniversaire())
                    && ! Auth::user()->aVuLInfoAnniversaire($qui->prochainAnniversaire()?->year ?? 0),
            ];
        };

        $me = Auth::user();

        return view('couple.dashboard', [
            'couple' => $couple,
            'partner' => $partner,
            'me' => $me,
            'partiesVo' => $couple->partiesVo()->latest()->limit(5)->get(),
            'missionsEnCours' => $couple->missionsSecreteEnCours()->count(),
            'missionsOuiNon' => $couple->missionsOuiNon()->where('statut', 'a_realiser')->get(),
            'meteoMoi' => $meteoInfo($maHumeur),
            'meteoPartenaire' => $meteoInfo($saHumeur),
            'meteoSynthese' => MeteoCouple::synthese($maHumeur, $saHumeur),
            'annivMoi' => $anniversaire($me, $partner, true),
            'annivPartenaire' => $anniversaire($partner, $me, false),
            'octobreRose' => [
                // Le 1er octobre uniquement, et une fois par année : le module
                // revient tous les ans mais pas pendant les 30 jours suivants,
                // pour ne pas harceler l'utilisateur chaque jour du mois.
                'due' => today()->month === 10
                    && today()->day === 1
                    && ! $me->aVuLOctobreRose(today()->year),
                'partenaire' => $partner?->name,
                // Le titre s'adresse à celui qui lit : une femme voit son partenaire
                // la chercher (« Abdoul pense à toi »), un homme est invité à
                // penser à elle (« Pense à Penda aujourd'hui »). Sans genre
                // renseigné, on garde la formulation neutre.
                'titre' => $me->genreEst('Femme')
                    ? $partner?->name." pense à toi aujourd'hui"
                    : 'Pense à '.($partner?->name ?? '')." aujourd'hui",
            ],
        ]);
    }

    public function activite(Request $request): JsonResponse
    {
        ActivityService::touch($request->user());

        $couple = $request->user()->coupleModel;
        $partner = $couple->partnerOf($request->user());

        $ligne = fn ($user) => [
            'present' => ! is_null($user?->last_active_at),
            'enLigne' => $user?->last_active_at && $user->last_active_at->diffInMinutes() < 1,
            'heure' => $user?->last_active_at ? $user->last_active_at->diffForHumans(null, CarbonInterface::DIFF_ABSOLUTE) : null,
            'aujourdhui' => $user?->last_active_at?->isToday() ?? false,
        ];

        return response()->json([
            'moi' => $ligne($request->user()),
            'partenaire' => $ligne($partner),
        ]);
    }

    public function setup(): View
    {
        $user = Auth::user();

        return view('couple.setup', [
            'couple' => $user->coupleModel,
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->coupleModel && $user->coupleModel->isLinked()) {
            return back()->with('flash', ['type' => 'info', 'message' => 'Ton couple est déjà lié.']);
        }

        $couple = $user->coupleModel;

        if (! $couple) {
            $couple = Couple::create([
                'code_unique' => Couple::generateCode(),
                'user1_id' => $user->id,
                'streak' => 0,
                'score_total' => 0,
            ]);
            $user->forceFill(['couple_id' => $couple->id])->save();
        } else {
            $couple->forceFill(['user1_id' => $user->id])->save();
        }

        ActivityService::touch($user);

        return back()->with('flash', ['type' => 'success', 'message' => 'Ton code de couple a été généré. Envoie-le à ton/ta partenaire !']);
    }

    public function link(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->coupleModel && $user->coupleModel->isLinked()) {
            return back()->with('flash', ['type' => 'info', 'message' => 'Ton couple est déjà lié.']);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]+$/'],
        ]);

        $code = strtoupper(trim($data['code']));

        $couple = Couple::where('code_unique', $code)->first();

        if (! $couple) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Code introuvable. Vérifie le code de ton/ta partenaire.']);
        }

        if (! is_null($couple->user2_id)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Ce couple a déjà deux membres liés.']);
        }

        if ($couple->user1_id === $user->id) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Tu es déjà dans ce couple.']);
        }

        $couple->forceFill(['user2_id' => $user->id]);
        $couple->score_total = $couple->score_total ?? 0;
        $couple->save();

        $user->forceFill(['couple_id' => $couple->id])->save();

        ActivityService::touch($user);
        ActivityService::touch($couple->user1);

        app(PushService::class)->sendToUser(
            $couple->user1,
            ['title' => '💞 Couple lié !', 'body' => $user->name.' a rejoint votre couple. Prêt·e à jouer ?', 'url' => '/']
        );

        return redirect()->route('dashboard')->with('flash', ['type' => 'success', 'message' => 'Félicitations, votre couple est lié !']);
    }

    public function leave(Request $request): RedirectResponse
    {
        $user = $request->user();
        $couple = $user->coupleModel;

        if ($couple) {
            if ($couple->user1_id === $user->id) {
                $couple->user1_id = null;
            } else {
                $couple->user2_id = null;
            }
            $couple->save();
        }

        $user->forceFill(['couple_id' => null])->save();

        return redirect()->route('couple.setup')->with('flash', ['type' => 'info', 'message' => 'Tu as quitté le couple.']);
    }
}
