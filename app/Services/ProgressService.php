<?php

namespace App\Services;

use App\Models\BookProgress;
use App\Models\Unit;
use App\Models\UnitProgress;
use Illuminate\Support\Carbon;

class ProgressService
{
    public const TOTAL_EXERCISES = 10;
    public const REPETITIONS_TO_MASTER = 10;

    /**
     * Reps at and above which exercises 8 (Organize Definition) and 9 (Organize Example)
     * become optional — the user can complete a cycle without them.
     */
    public const OPTIONAL_FROM_REPS = 5;

    /**
     * Returns the list of exercise numbers required to mark a unit cycle as completed,
     * given the user's current repetition count for that unit.
     *
     * Exercises 8 and 9 (Organize Definition / Example) become optional from
     * OPTIONAL_FROM_REPS onwards. Read (10) is always required and serves as
     * the gating exercise for marking words as known.
     *
     * @return array<int>
     */
    public function requiredExercises(int $repetitionCount): array
    {
        if ($repetitionCount >= self::OPTIONAL_FROM_REPS) {
            return [1, 2, 3, 4, 5, 6, 7, 10];
        }
        return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    }

    public function isExerciseOptional(int $exerciseNumber, int $repetitionCount): bool
    {
        return $repetitionCount >= self::OPTIONAL_FROM_REPS && in_array($exerciseNumber, [8, 9], true);
    }

    /**
     * Read (10) is the gating exercise — it can only be started once the user
     * has completed every other required exercise for the current cycle. This
     * lets Read serve as the moment to mark words as known with confidence.
     */
    public function canStartRead(int $userId, int $unitId): bool
    {
        $progress = UnitProgress::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->first();

        $repetition = $progress?->repetition_count ?? 0;
        $completed = $progress?->exercises_completed ?? [];

        $required = $this->requiredExercises($repetition);
        foreach ($required as $n) {
            if ($n === 10) continue;
            if (! in_array($n, $completed, true)) return false;
        }
        return true;
    }

    public function getOrCreate(int $userId, int $unitId): UnitProgress
    {
        return UnitProgress::firstOrCreate(
            ['user_id' => $userId, 'unit_id' => $unitId],
            ['repetition_count' => 0, 'exercises_completed' => []],
        );
    }

    public function isExerciseCompleted(int $userId, int $unitId, int $exerciseNumber): bool
    {
        $progress = UnitProgress::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->first();

        return $progress?->isExerciseCompleted($exerciseNumber) ?? false;
    }

    public function markExerciseCompleted(int $userId, int $unitId, int $exerciseNumber): UnitProgress
    {
        $progress = $this->getOrCreate($userId, $unitId);
        $progress->completeExercise($exerciseNumber);
        $progress->save();

        // Determine which exercises are required for this rep count (9 and 10 optional from rep 5)
        $required = $this->requiredExercises($progress->repetition_count);
        $completed = $progress->exercises_completed ?? [];
        $allRequiredDone = true;
        foreach ($required as $r) {
            if (! in_array($r, $completed, true)) {
                $allRequiredDone = false;
                break;
            }
        }

        if ($allRequiredDone) {
            $this->completeUnitReview($userId, $unitId);
        } else {
            $unit = Unit::find($unitId);
            if ($unit) {
                $this->touchBookActivity($userId, $unit->book_id);
            }
        }

        return $progress->refresh();
    }

    public function areAllExercisesCompleted(int $userId, int $unitId): bool
    {
        $progress = UnitProgress::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->first();

        return $progress?->areAllExercisesCompleted(self::TOTAL_EXERCISES) ?? false;
    }

    /**
     * Completar revisión de la unidad: incrementa repetition_count, resetea ejercicios
     * y registra la unidad como completada para el tracking de actividad diaria.
     */
    public function completeUnitReview(int $userId, int $unitId): UnitProgress
    {
        $progress = $this->getOrCreate($userId, $unitId);

        $progress->repetition_count++;
        $progress->last_review = now();
        $progress->resetExercises();
        $progress->save();

        \App\Models\UnitCompletionLog::create([
            'user_id' => $userId,
            'unit_id' => $unitId,
            'completed_at' => now(),
        ]);

        $unit = Unit::find($unitId);
        if ($unit) {
            $this->touchBookActivity($userId, $unit->book_id);
        }

        return $progress;
    }

    /**
     * Horas necesarias entre revisiones según la fórmula exponencial:
     *     hoursNeeded = ceil( pow(repetitionCount, 1.6) * 24 )
     */
    public function hoursNeededForReps(int $repetitionCount): int
    {
        $reps = max($repetitionCount, 1);
        return (int) ceil(pow($reps, 1.6) * 24);
    }

    /**
     * Días equivalentes (para mostrar "X días" en la UI).
     */
    public function daysNeededForReps(int $repetitionCount): int
    {
        return (int) ceil($this->hoursNeededForReps($repetitionCount) / 24);
    }

    /**
     * ¿Está disponible esta unidad según spaced repetition?
     * Disponible si: nunca revisada, mid-cycle, o han pasado las horas suficientes.
     */
    public function isUnitAvailable(int $userId, int $unitId): bool
    {
        $progress = UnitProgress::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->first();

        if ($progress === null || $progress->last_review === null) {
            return true;
        }

        // Mid-cycle: algunos ejercicios hechos, no todos los requeridos
        $required = $this->requiredExercises($progress->repetition_count);
        $completed = $progress->exercises_completed ?? [];
        $doneCount = 0;
        foreach ($required as $r) {
            if (in_array($r, $completed, true)) $doneCount++;
        }
        if ($doneCount > 0 && $doneCount < count($required)) {
            return true;
        }

        $hoursNeeded = $this->hoursNeededForReps($progress->repetition_count);
        $hoursSinceReview = (int) floor((float) $progress->last_review->diffInHours(now()));

        return $hoursSinceReview >= $hoursNeeded;
    }

    /**
     * Horas que faltan para que la unidad esté disponible. 0 si ya lo está.
     */
    public function hoursUntilAvailable(int $userId, int $unitId): int
    {
        if ($this->isUnitAvailable($userId, $unitId)) return 0;

        $progress = UnitProgress::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->first();

        if ($progress === null || $progress->last_review === null) return 0;

        $hoursNeeded = $this->hoursNeededForReps($progress->repetition_count);
        $hoursSinceReview = (int) floor((float) $progress->last_review->diffInHours(now()));

        return max(0, $hoursNeeded - $hoursSinceReview);
    }

    /**
     * Días que faltan, redondeados hacia arriba. 0 si ya está disponible.
     */
    public function daysUntilAvailable(int $userId, int $unitId): int
    {
        $hours = $this->hoursUntilAvailable($userId, $unitId);
        return $hours === 0 ? 0 : (int) ceil($hours / 24);
    }

    /**
     * Texto humano del tiempo que falta: "5 horas", "2 días", etc.
     */
    public function timeUntilAvailableLabel(int $userId, int $unitId): string
    {
        $hours = $this->hoursUntilAvailable($userId, $unitId);
        if ($hours === 0) return '';
        if ($hours < 24) {
            return $hours === 1 ? 'vuelve en 1 hora' : "vuelve en {$hours} horas";
        }
        $days = (int) ceil($hours / 24);
        return $days === 1 ? 'vuelve mañana' : "vuelve en {$days} días";
    }

    /**
     * Próxima fecha en la que la unidad estará disponible para revisar.
     */
    public function nextReviewDate(int $userId, int $unitId): ?Carbon
    {
        $progress = UnitProgress::where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->first();

        if ($progress === null || $progress->last_review === null) {
            return null;
        }

        return $progress->last_review->copy()->addDays($this->daysNeededForReps($progress->repetition_count));
    }

    public function touchBookActivity(int $userId, int $bookId): void
    {
        $bp = BookProgress::firstOrCreate(
            ['user_id' => $userId, 'book_id' => $bookId],
            ['start_date' => now(), 'last_activity' => now()],
        );

        if (! $bp->wasRecentlyCreated) {
            $bp->last_activity = now();
            $bp->save();
        }
    }

    public function getBookStatistics(int $userId, int $bookId): \App\DTOs\BookStatistics
    {
        $unitIds = \App\Models\Unit::where('book_id', $bookId)->pluck('id');
        $totalUnits = $unitIds->count();

        $progressRows = UnitProgress::where('user_id', $userId)
            ->whereIn('unit_id', $unitIds)
            ->pluck('repetition_count', 'unit_id');

        $totalReps = (int) $progressRows->sum();
        $mastered = $progressRows->filter(fn ($r) => $r >= self::REPETITIONS_TO_MASTER)->count();
        $inProgress = $progressRows->filter(fn ($r) => $r > 0 && $r < self::REPETITIONS_TO_MASTER)->count();
        $notStarted = $totalUnits - $mastered - $inProgress;

        $bookProgress = BookProgress::where('user_id', $userId)
            ->where('book_id', $bookId)
            ->first();

        return new \App\DTOs\BookStatistics(
            bookId: $bookId,
            bookProgress: $bookProgress,
            totalUnits: $totalUnits,
            totalRepetitions: $totalReps,
            unitsMastered: $mastered,
            unitsInProgress: $inProgress,
            unitsNotStarted: $notStarted,
        );
    }

    /**
     * @return array<int, \App\DTOs\BookStatistics> indexed by book_id
     */
    public function getAllBooksStatistics(int $userId): array
    {
        $bookIds = \App\Models\Book::orderBy('order')->pluck('id');
        $stats = [];
        foreach ($bookIds as $bookId) {
            $stats[$bookId] = $this->getBookStatistics($userId, $bookId);
        }
        return $stats;
    }

    /**
     * Distribución de repeticiones por bucket 0..10 (10 = "10 o más").
     * Resultado: array indexado 0..10, valor = número de unidades en ese bucket.
     *
     * @return array<int, int>
     */
    public function getRepetitionDistribution(int $userId, int $bookId): array
    {
        $unitIds = \App\Models\Unit::where('book_id', $bookId)->pluck('id');

        $progressByUnit = UnitProgress::where('user_id', $userId)
            ->whereIn('unit_id', $unitIds)
            ->pluck('repetition_count', 'unit_id');

        $distribution = array_fill(0, 11, 0);
        foreach ($unitIds as $unitId) {
            $reps = (int) ($progressByUnit[$unitId] ?? 0);
            $key = $reps >= 10 ? 10 : $reps;
            $distribution[$key]++;
        }
        return $distribution;
    }

    /**
     * @return array<int, array<int, int>> indexed by book_id, then by bucket 0..10
     */
    public function getAllRepetitionDistributions(int $userId): array
    {
        $bookIds = \App\Models\Book::orderBy('order')->pluck('id');
        $result = [];
        foreach ($bookIds as $bookId) {
            $result[$bookId] = $this->getRepetitionDistribution($userId, $bookId);
        }
        return $result;
    }

    /**
     * Daily completed-units count for the last $days days, ending today.
     * Each unit completion = the user finished all 10 exercises of a unit (1 repetition).
     * Returns array of ['date' => Carbon, 'count' => int] in chronological order.
     *
     * @return array<int, array{date: \Illuminate\Support\Carbon, count: int}>
     */
    public function getDailyActivity(int $userId, int $days = 30): array
    {
        $end = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = \App\Models\UnitCompletionLog::where('user_id', $userId)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'count' => (int) ($rows[$key] ?? 0),
            ];
        }
        return $result;
    }
}
