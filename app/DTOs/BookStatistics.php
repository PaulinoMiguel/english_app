<?php

namespace App\DTOs;

use App\Models\BookProgress;
use Carbon\Carbon;

class BookStatistics
{
    public function __construct(
        public readonly int $bookId,
        public readonly ?BookProgress $bookProgress,
        public readonly int $totalUnits,
        public readonly int $totalRepetitions,
        public readonly int $unitsMastered,
        public readonly int $unitsInProgress,
        public readonly int $unitsNotStarted,
    ) {
    }

    public const REPS_TO_MASTER = 10;

    public function totalPossibleRepetitions(): int
    {
        return $this->totalUnits * self::REPS_TO_MASTER;
    }

    /** Overall percentage based on repetitions (0..100) */
    public function overallProgress(): float
    {
        $total = $this->totalPossibleRepetitions();
        if ($total === 0) return 0.0;
        return ($this->totalRepetitions / $total) * 100;
    }

    public function masterProgress(): float
    {
        if ($this->totalUnits === 0) return 0.0;
        return ($this->unitsMastered / $this->totalUnits) * 100;
    }

    public function hasStarted(): bool
    {
        return $this->bookProgress?->start_date !== null;
    }

    public function isCompleted(): bool
    {
        return $this->unitsMastered >= $this->totalUnits;
    }

    public function daysElapsed(): int
    {
        if (! $this->hasStarted()) return 0;
        return (int) $this->bookProgress->start_date->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function repetitionsPerDay(): float
    {
        if (! $this->hasStarted() || $this->totalRepetitions === 0) return 0.0;
        $days = max($this->daysElapsed(), 1);
        return $this->totalRepetitions / $days;
    }

    public function remainingRepetitions(): int
    {
        return max(0, $this->totalPossibleRepetitions() - $this->totalRepetitions);
    }

    public function estimatedDaysToComplete(): int
    {
        if (! $this->hasStarted()) return 0;
        if ($this->remainingRepetitions() === 0) return 0;
        $rate = $this->repetitionsPerDay();
        if ($rate <= 0) return 0;
        return (int) ceil($this->remainingRepetitions() / $rate);
    }

    public function estimatedCompletionDate(): ?Carbon
    {
        $days = $this->estimatedDaysToComplete();
        if ($days === 0) return null;
        return now()->addDays($days);
    }

    public function timeRemainingText(): string
    {
        if (! $this->hasStarted()) return 'No iniciado';
        if ($this->remainingRepetitions() === 0) return 'Completado';
        if ($this->repetitionsPerDay() === 0.0) return 'Comienza a practicar';

        $days = $this->estimatedDaysToComplete();

        if ($days < 7) {
            return 'Aproximadamente '.$days.' '.($days === 1 ? 'día' : 'días');
        }
        if ($days < 30) {
            $weeks = (int) ceil($days / 7);
            return 'Aproximadamente '.$weeks.' '.($weeks === 1 ? 'semana' : 'semanas');
        }
        if ($days < 365) {
            $months = (int) ceil($days / 30);
            return 'Aproximadamente '.$months.' '.($months === 1 ? 'mes' : 'meses');
        }
        $years = (int) floor($days / 365);
        $remainingMonths = (int) ceil(($days % 365) / 30);
        if ($remainingMonths > 0) {
            return 'Aproximadamente '.$years.' '.($years === 1 ? 'año' : 'años').' y '.$remainingMonths.' '.($remainingMonths === 1 ? 'mes' : 'meses');
        }
        return 'Aproximadamente '.$years.' '.($years === 1 ? 'año' : 'años');
    }
}
