<?php

namespace App\Http\Controllers;

use App\Models\Couple;
use App\Models\MeteoCouple;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\PushService;
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
            ];
        };

        $anniversaire = function (User $user): array {
            $prochain = $user->prochainAnniversaire();

            return [
                'name' => $user->name,
                'date' => $prochain,
                'jours' => $prochain ? (int) today()->startOfDay()->diffInDays($prochain) : null,
            ];
        };

        return view('couple.dashboard', [
            'couple' => $couple,
            'partner' => $partner,
            'me' => Auth::user(),
            'partiesVo' => $couple->partiesVo()->latest()->limit(5)->get(),
            'missionsEnCours' => $couple->missionsSecreteEnCours()->count(),
            'missionsOuiNon' => $couple->missionsOuiNon()->where('statut', 'a_realiser')->get(),
            'meteoMoi' => $meteoInfo($maHumeur),
            'meteoPartenaire' => $meteoInfo($saHumeur),
            'meteoSynthese' => MeteoCouple::synthese($maHumeur, $saHumeur),
            'annivMoi' => $anniversaire(Auth::user()),
            'annivPartenaire' => $anniversaire($partner),
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
            'heure' => $user?->last_active_at ? $user->last_active_at->diffForHumans() : null,
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
