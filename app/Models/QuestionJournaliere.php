<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['couple_id', 'question_id', 'jour'])]
class QuestionJournaliere extends Model
{
    use HasFactory;

    protected $table = 'questions_journalieres';

    protected function casts(): array
    {
        return [
            'jour' => 'date',
        ];
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionDuJour::class, 'question_id');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseQuestionJournaliere::class, 'question_journaliere_id');
    }

    /**
     * Récupère (ou crée) la question du jour du couple.
     *
     * @return array{0: ?QuestionJournaliere, 1: bool} la question et « créée maintenant ? »
     */
    public static function genererPourCouple(Couple $couple): array
    {
        $today = today();

        $find = fn () => self::where('couple_id', $couple->id)
            ->whereDate('jour', $today)
            ->with('question')
            ->first();

        $existing = $find();
        if ($existing) {
            return [$existing, false];
        }

        $used = self::where('couple_id', $couple->id)->pluck('question_id');
        $question = QuestionDuJour::whereNotIn('id', $used)->inRandomOrder()->first()
            ?? QuestionDuJour::inRandomOrder()->first();

        if (! $question) {
            return [null, false];
        }

        try {
            self::create([
                'couple_id' => $couple->id,
                'question_id' => $question->id,
                'jour' => $today,
            ]);
        } catch (\Throwable) {
            return [$find(), false];
        }

        return [$find(), true];
    }
}
