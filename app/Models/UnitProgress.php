<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitProgress extends Model
{
    protected $table = 'unit_progress';

    protected $fillable = [
        'user_id',
        'unit_id',
        'repetition_count',
        'last_review',
        'exercises_completed',
    ];

    protected $casts = [
        'last_review' => 'datetime',
        'exercises_completed' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function isExerciseCompleted(int $exerciseNumber): bool
    {
        return in_array($exerciseNumber, $this->exercises_completed ?? [], true);
    }

    public function completeExercise(int $exerciseNumber): void
    {
        $completed = $this->exercises_completed ?? [];
        if (! in_array($exerciseNumber, $completed, true)) {
            $completed[] = $exerciseNumber;
            sort($completed);
            $this->exercises_completed = $completed;
        }
    }

    public function areAllExercisesCompleted(int $totalExercises = 10): bool
    {
        return count($this->exercises_completed ?? []) >= $totalExercises;
    }

    public function resetExercises(): void
    {
        $this->exercises_completed = [];
    }
}
