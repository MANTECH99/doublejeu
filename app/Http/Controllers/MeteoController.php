<?php

namespace App\Http\Controllers;

use App\Models\MeteoCouple;
use App\Services\ActivityService;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class MeteoController extends Controller
{
    public const SUGGESTIONS = [
        '🌿 Va faire une petite marche et invite ton/ta partenaire à respirer avec toi.',
        '📞 Passe un appel juste pour dire bonjour et prendre des nouvelles.',
        '🍽️ Prépare son repas préféré ce soir, sans prévenir.',
        '📝 Écris-lui un mot doux et glisse-le près de lui/elle.',
        '🎶 Crée une playlist « notre refuge » et partage-la.',
        '🧘 Propose 10 minutes de calme ensemble, sans écran.',
        '☕ Offre-lui sa boisson chaude préférée, peut-être au lit.',
        '🌅 Envoyez-vous chacun une photo du ciel au même moment.',
        '🎲 Lancez une partie de l\'un de vos jeux préférés, même à distance.',
        '💆 Offre-lui 5 minutes de massage des épaules, sans rien demander en retour.',
    ];

    public const SUGGESTIONS_RECONFORT = [
        '🫂 Posez les téléphones et parlez de ce qui pèse vraiment, sans jugement.',
        '🤗 Un câlin (ou un long appel) d\'abord : les mots viendront après.',
        '🌧️ Rappelez-vous que la tempête ne dure pas : dites-vous « on y arrive ».',
        '🍵 Préparez une boisson chaude à l\'autre, un geste simple qui apaise.',
        '💌 Reconnaissez un point positif chez l\'autre aujourd\'hui, même petit.',
        '🚶 Marchez côte à côte en silence un instant, ça resserre le lien.',
        '😌 Choisissez ensemble UNE chose à faire ce soir pour vous faire du bien.',
    ];

    public function index(): View
    {
        ActivityService::touch(Auth::user());

        return view('jeux.meteo.index', [
            'couple' => Auth::user()->coupleModel,
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $couple = $request->user()->coupleModel;
        $user = $request->user();
        $partner = $couple->partnerOf($user);

        $meteo = MeteoCouple::aujourdhuiPour($couple);

        $maHumeur = $meteo?->humeurActuellePour($user->id);
        $saHumeur = $meteo?->humeurActuellePour($partner->id);
        $revelee = (bool) $meteo?->estComplet();

        $doy = (int) now()->format('z');
        $suggestion = self::SUGGESTIONS[$doy % count(self::SUGGESTIONS)];
        $suggestionReconfort = self::SUGGESTIONS_RECONFORT[$doy % count(self::SUGGESTIONS_RECONFORT)];

        $historique = MeteoCouple::where('couple_id', $couple->id)
            ->whereDate('jour', '<=', today())
            ->orderByDesc('jour')
            ->take(14)
            ->get()
            ->reverse()
            ->map(fn (MeteoCouple $m) => [
                'jour' => $m->jour->format('d/m'),
                'moi' => $m->partagesPour($user->id),
                'lui' => $m->partagesPour($partner->id),
            ])
            ->values();

        return response()->json([
            'jour' => today()->format('d/m/Y'),
            'jaiRepondu' => ! is_null($maHumeur),
            'ilElleARepondu' => ! is_null($saHumeur),
            'revelee' => $revelee,
            'maHumeur' => $maHumeur,
            'monCommentaire' => $meteo?->commentaireActuellePour($user->id),
            'saHumeur' => $saHumeur,
            'saCommentaire' => $meteo?->commentaireActuellePour($partner->id),
            'mesPartages' => $meteo?->partagesPour($user->id) ?? [],
            'sesPartages' => $meteo?->partagesPour($partner->id) ?? [],
            'maxPartages' => MeteoCouple::MAX_CHECKINS_PAR_JOUR,
            'lesDeuxMauvais' => $revelee && $meteo->lesDeuxMauvais(),
            'synthese' => MeteoCouple::synthese($maHumeur, $saHumeur),
            'suggestion' => $suggestion,
            'suggestionReconfort' => $suggestionReconfort,
            'maSuggestion' => $meteo?->suggestionPour($user->id),
            'saSuggestion' => $meteo?->suggestionPour($partner->id),
            'historique' => $historique,
            'meteos' => MeteoCouple::METEOS,
            'partenaire' => $partner->name,
        ]);
    }

    public function checkin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'humeur' => ['required', 'string', 'in:'.implode(',', array_keys(MeteoCouple::METEOS))],
            'commentaire' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Une météo valide est requise.'], 422);
        }

        $couple = $request->user()->coupleModel;
        $user = $request->user();
        $partner = $couple->partnerOf($user);

        $meteo = MeteoCouple::firstOrCreate([
            'couple_id' => $couple->id,
            'jour' => today(),
        ]);

        $nbPartages = $meteo->nombrePartagesPour($user->id);
        if ($nbPartages >= MeteoCouple::MAX_CHECKINS_PAR_JOUR) {
            return response()->json(['error' => 'Tu as déjà partagé tes '.MeteoCouple::MAX_CHECKINS_PAR_JOUR.' humeurs du jour.'], 422);
        }

        $isUser1 = $couple->user1_id === $user->id;
        $humeurCol = $isUser1 ? ($nbPartages === 0 ? 'humeur_user1' : 'humeur_user1_2') : ($nbPartages === 0 ? 'humeur_user2' : 'humeur_user2_2');
        $commentaireCol = $isUser1 ? ($nbPartages === 0 ? 'commentaire_user1' : 'commentaire_user1_2') : ($nbPartages === 0 ? 'commentaire_user2' : 'commentaire_user2_2');

        $meteo->forceFill([
            $humeurCol => $request->input('humeur'),
            $commentaireCol => $request->input('commentaire'),
        ])->save();

        app(PushService::class)->sendToUser($partner, [
            'title' => '🌦️ Météo du couple',
            'body' => $user->name.' a partagé son humeur. Et toi ?',
            'url' => route('meteo.index'),
        ]);

        if ($meteo->lesDeuxMauvais()) {
            foreach ($couple->users as $u) {
                app(PushService::class)->sendToUser($u, [
                    'title' => '⚠️ Alerte météo',
                    'body' => 'Vous vous sentez tous les deux mal en ce moment. Cliquez pour un conseil pour apaiser la tempête.',
                    'url' => route('meteo.index'),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function suggestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'suggestion' => ['required', 'string', 'max:280'],
        ]);

        $couple = $request->user()->coupleModel;
        $user = $request->user();

        $text = trim($data['suggestion']);
        if ($text === '') {
            return response()->json(['error' => 'Écris d\'abord ton idée.'], 422);
        }

        $meteo = MeteoCouple::firstOrCreate([
            'couple_id' => $couple->id,
            'jour' => today(),
        ]);

        $col = $couple->user1_id === $user->id ? 'suggestion_user1' : 'suggestion_user2';

        if (! empty($meteo->{$col})) {
            return response()->json(['error' => 'Tu as déjà partagé ton idée aujourd\'hui.'], 422);
        }

        $meteo->forceFill([$col => $text])->save();

        $partner = $couple->partnerOf($user);

        app(PushService::class)->sendToUser($partner, [
            'title' => '💡 Idée du couple',
            'body' => $user->name.' propose une idée pour embellir le ciel. Viens la voir !',
            'url' => route('meteo.index'),
        ]);

        return response()->json(['ok' => true]);
    }
}
