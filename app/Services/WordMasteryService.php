<?php

namespace App\Services;

use App\Models\CycleWordFault;
use App\Models\Unit;
use App\Models\UnitProgress;
use App\Models\WordMastery;
use Illuminate\Support\Collection;

class WordMasteryService
{
    /**
     * Words that should still be exercised for this user in this unit —
     * everything except those currently "known" (mastery row exists and
     * has not yet expired against the user's current repetition count).
     */
    public function activeWordsForUnit(int $userId, Unit $unit): Collection
    {
        $rep = $this->currentRep($userId, $unit->id);

        $knownWordIds = WordMastery::where('user_id', $userId)
            ->whereIn('word_id', $unit->words->pluck('id'))
            ->where('expires_at_rep', '>', $rep)
            ->pluck('word_id')
            ->all();

        if (empty($knownWordIds)) {
            return $unit->words;
        }

        return $unit->words->reject(fn ($w) => in_array($w->id, $knownWordIds, true))->values();
    }

    /**
     * Records a first-attempt fault for a word in the current cycle. The
     * unique constraint guarantees only the first call per (user,unit,word,
     * cycle,exercise) lands; later faults during retries are silently ignored.
     */
    public function recordFault(int $userId, int $unitId, int $wordId, int $exerciseNumber, int $repetitionCount): void
    {
        CycleWordFault::firstOrCreate(
            [
                'user_id' => $userId,
                'unit_id' => $unitId,
                'word_id' => $wordId,
                'repetition_count' => $repetitionCount,
                'exercise_number' => $exerciseNumber,
            ],
            ['faulted_at' => now()],
        );
    }

    /**
     * Convenience wrapper: looks up the current repetition_count and records
     * a fault. Use from exercise components that already have the Unit loaded.
     */
    public function recordExerciseFault(int $userId, Unit $unit, int $wordId, int $exerciseNumber): void
    {
        $rep = $this->currentRep($userId, $unit->id);
        $this->recordFault($userId, $unit->id, $wordId, $exerciseNumber, $rep);
    }

    /**
     * True if the word has any fault recorded in the current cycle, in any
     * exercise. Used to gate the "lo sé" button in Read flashcard.
     */
    public function hasFaultInCycle(int $userId, int $unitId, int $wordId, int $repetitionCount): bool
    {
        return CycleWordFault::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->where('word_id', $wordId)
            ->where('repetition_count', $repetitionCount)
            ->exists();
    }

    public function canMarkKnown(int $userId, int $unitId, int $wordId, int $repetitionCount): bool
    {
        return ! $this->hasFaultInCycle($userId, $unitId, $wordId, $repetitionCount);
    }

    /**
     * Marks a word as known. If a previous (now-expired) mastery row exists,
     * bump backoff_level (3 → 5 → 8 cycles). Otherwise create at level 0.
     *
     * Should be called AFTER the unit cycle has been incremented so that
     * marked_at_rep reflects the cycle that just completed.
     */
    public function markKnown(int $userId, int $wordId, int $currentRep): WordMastery
    {
        $existing = WordMastery::where('user_id', $userId)
            ->where('word_id', $wordId)
            ->first();

        $level = $existing ? min($existing->backoff_level + 1, count(WordMastery::BACKOFF_INTERVALS) - 1) : 0;
        $interval = WordMastery::intervalForLevel($level);

        return WordMastery::updateOrCreate(
            ['user_id' => $userId, 'word_id' => $wordId],
            [
                'marked_at_rep' => $currentRep,
                'expires_at_rep' => $currentRep + $interval,
                'backoff_level' => $level,
            ],
        );
    }

    public function isKnown(int $userId, int $wordId, int $currentRep): bool
    {
        return WordMastery::where('user_id', $userId)
            ->where('word_id', $wordId)
            ->where('expires_at_rep', '>', $currentRep)
            ->exists();
    }

    /**
     * Word IDs known by the user across all of a unit's words at the given rep.
     *
     * @return array<int>
     */
    public function knownWordIdsForUnit(int $userId, Unit $unit): array
    {
        $rep = $this->currentRep($userId, $unit->id);
        return WordMastery::where('user_id', $userId)
            ->whereIn('word_id', $unit->words->pluck('id'))
            ->where('expires_at_rep', '>', $rep)
            ->pluck('word_id')
            ->all();
    }

    private function currentRep(int $userId, int $unitId): int
    {
        return (int) (UnitProgress::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->value('repetition_count') ?? 0);
    }
}
