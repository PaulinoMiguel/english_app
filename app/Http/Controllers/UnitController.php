<?php

namespace App\Http\Controllers;

use App\Models\ExerciseType;
use App\Models\Unit;
use App\Models\UnitProgress;
use App\Services\ProgressService;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function show(Unit $unit, ProgressService $service)
    {
        $userId = auth()->id();

        if (! $service->isUnitAvailable($userId, $unit->id)) {
            $label = $service->timeUntilAvailableLabel($userId, $unit->id);
            return redirect()
                ->route('books.show', $unit->book_id)
                ->with('flash_message', "Esta unidad está en descanso — {$label}.");
        }

        $unit->load(['book', 'words']);

        $progress = UnitProgress::firstOrNew(
            ['user_id' => $userId, 'unit_id' => $unit->id],
            ['repetition_count' => 0, 'exercises_completed' => []],
        );

        $exerciseTypes = ExerciseType::orderBy('number')->get();

        $completed = $progress->exercises_completed ?? [];

        $optionalExercises = [];
        if ($progress->repetition_count >= ProgressService::OPTIONAL_FROM_REPS) {
            $optionalExercises = [9, 10];
        }

        return view('units.show', compact('unit', 'progress', 'exerciseTypes', 'completed', 'optionalExercises'));
    }
}
