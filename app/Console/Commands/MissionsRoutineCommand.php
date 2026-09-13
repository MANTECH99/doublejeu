<?php

namespace App\Console\Commands;

use App\Http\Controllers\MissionSecreteController;
use App\Models\Couple;
use App\Services\PushService;
use Illuminate\Console\Command;

class MissionsRoutineCommand extends Command
{
    protected $signature = 'missions:routine';

    protected $description = 'Attribue les missions quotidiennes (00h) et notifie la question du soir (20h).';

    public function handle(): int
    {
        $couples = Couple::with('users')->whereNotNull('user2_id')->get();
        $created = 0;
        $notified = 0;

        foreach ($couples as $couple) {
            foreach ($couple->users as $user) {
                $localTodayStr = $user->localToday()->toDateString();

                // 00h : créer la mission du jour si absente (idempotent, également créée à la demande dans l'app).
                [$mission, $cree] = MissionSecreteController::genererPourUser($couple, $user);

                if ($cree && $mission) {
                    app(PushService::class)->sendToUser($user, [
                        'title' => '🕵️ Une nouvelle mission secrète t\'attend !',
                        'body' => 'Va sur la page Mission secrète pour l\'accepter ou la refuser.',
                        'url' => route('mission.index'),
                    ]);

                    $created++;
                }

                // 20h : notification de la question (une seule fois par jour).
                $deadline = $user->deadlineSoir()->setTimezone(config('app.timezone', 'UTC'));
                $dansFenetre = now()->gte($deadline) && now()->lt($deadline->copy()->addMinutes(10));
                $dejaNotifie = $user->mission_question_notif_jour?->toDateString() === $localTodayStr;

                if ($dansFenetre && ! $dejaNotifie) {
                    $partenaire = $couple->partnerOf($user);
                    $partnerToday = $partenaire->localToday()->toDateString();

                    $missionPartenaire = $partenaire
                        ? $couple->missionsSecrettes()
                            ->where('joueur_cible_id', $partenaire->id)
                            ->whereDate('date_mission', $partnerToday)
                            ->first()
                        : null;

                    if ($missionPartenaire) {
                        app(PushService::class)->sendToUser($user, [
                            'title' => '🌙 L\'heure de la question du soir !',
                            'body' => 'Ton/ta partenaire a-t-il/elle fait une mission secrète aujourd\'hui ?',
                            'url' => route('mission.index'),
                        ]);

                        $user->forceFill(['mission_question_notif_jour' => now()])->save();
                        $notified++;
                    }
                }
            }
        }

        $this->info("{$created} mission(s) créée(s), {$notified} notification(s) de question envoyée(s).");

        return self::SUCCESS;
    }
}
