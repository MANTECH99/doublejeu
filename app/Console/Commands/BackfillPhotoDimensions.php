<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:backfill-photo-dimensions')]
#[Description('Lit la taille native de chaque photo de la discussion et la stocke sur le message (réservation de hauteur côté client).')]
class BackfillPhotoDimensions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $messages = Message::query()
            ->whereNotNull('photo_path')
            ->whereNull('photo_w')
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            $this->info('Aucune photo à traiter.');

            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($messages->count());

        foreach ($messages as $message) {
            $path = Storage::disk('public')->path($message->photo_path);
            if (! is_file($path)) {
                $failed++;

                $bar->advance();

                continue;
            }

            $size = @getimagesize($path);
            if (! is_array($size) || $size[0] < 1 || $size[1] < 1) {
                $failed++;

                $bar->advance();

                continue;
            }

            $message->forceFill(['photo_w' => $size[0], 'photo_h' => $size[1]])->save();
            $updated++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$updated} photo(s) traitée(s), {$failed} échec(s).");

        return self::SUCCESS;
    }
}
