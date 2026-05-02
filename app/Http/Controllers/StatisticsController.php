<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\ProgressService;
use Illuminate\View\View;

class StatisticsController extends Controller
{
    public function index(ProgressService $service): View
    {
        $userId = auth()->id();
        $books = Book::orderBy('order')->get();
        $stats = $service->getAllBooksStatistics($userId);
        $distributions = $service->getAllRepetitionDistributions($userId);
        $dailyActivity = $service->getDailyActivity($userId, 30);

        return view('statistics.index', compact('books', 'stats', 'distributions', 'dailyActivity'));
    }
}
