<?php

namespace App\Console\Commands;

use App\Models\Couple;
use App\Models\QuestionJournaliere;
use App\Services\PushService;
use Illuminate\Console\Command;

class QuestionDuJourCommand extends Command
{
    protected $signature = 'question:dujour';

    protected $description = 'Crée la Question du Jour pour chaque couple et envoie les notifications.';

    public function handle(): int
    {
        $couples = Couple::with('users')->whereNotNull('user2_id')->get();
        $count = 0;

        foreach ($couples as $couple) {
            [$qj, $cree] = QuestionJournaliere::genererPourCouple($couple);
            if (! $cree || ! $qj) {
                continue;
            }

            foreach ($couple->users as $user) {
                app(PushService::class)->sendToUser($user, [
                    'title' => '🌅 La Question du jour est là !',
                    'body' => 'Réponds en secret pour découvrir la réponse de ton/ta partenaire.',
                    'url' => route('question.index'),
                ]);
            }

            $count++;
        }

        $this->info("{$count} question(s) du jour créée(s).");

        return self::SUCCESS;
    }
}
