<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Unit;
use App\Models\UnitProgress;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(): View
    {
        $userId = auth()->id();

        $books = Book::orderBy('order')->get();

        $stats = $books->mapWithKeys(function (Book $book) use ($userId) {
            $unitIds = Unit::where('book_id', $book->id)->pluck('id');
            $totalUnits = $unitIds->count();

            $progressByUnit = UnitProgress::where('user_id', $userId)
                ->whereIn('unit_id', $unitIds)
                ->pluck('repetition_count', 'unit_id');

            $mastered = $progressByUnit->filter(fn ($r) => $r >= 10)->count();
            $inProgress = $progressByUnit->filter(fn ($r) => $r > 0 && $r < 10)->count();
            $notStarted = $totalUnits - $mastered - $inProgress;
            $percent = $totalUnits > 0 ? (int) round(($mastered / $totalUnits) * 100) : 0;

            return [$book->id => [
                'total' => $totalUnits,
                'mastered' => $mastered,
                'in_progress' => $inProgress,
                'not_started' => $notStarted,
                'percent' => $percent,
            ]];
        });

        return view('books.index', compact('books', 'stats'));
    }

    public function show(Book $book, \App\Services\ProgressService $service): View
    {
        $userId = auth()->id();

        $units = $book->units()->get();

        $progressByUnit = UnitProgress::where('user_id', $userId)
            ->whereIn('unit_id', $units->pluck('id'))
            ->get()
            ->keyBy('unit_id');

        $availability = [];
        foreach ($units as $unit) {
            $availability[$unit->id] = [
                'available' => $service->isUnitAvailable($userId, $unit->id),
                'days_until' => $service->daysUntilAvailable($userId, $unit->id),
                'next_review' => $service->nextReviewDate($userId, $unit->id),
            ];
        }

        return view('books.show', compact('book', 'units', 'progressByUnit', 'availability'));
    }
}
