<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect(auth()->check() ? route('dashboard') : route('login'));
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [BookController::class, 'index'])->name('dashboard');
    Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics');
    Route::get('books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::get('units/{unit}', [UnitController::class, 'show'])->name('units.show');

    Volt::route('units/{unit}/exercises/listen-and-read', 'exercises.listen-and-read')
        ->name('units.exercises.listen-and-read');

    Volt::route('units/{unit}/exercises/multichoice-english', 'exercises.multichoice-english')
        ->name('units.exercises.multichoice-english');

    Volt::route('units/{unit}/exercises/multichoice-translation', 'exercises.multichoice-translation')
        ->name('units.exercises.multichoice-translation');

    Volt::route('units/{unit}/exercises/match-definition', 'exercises.match-definition')
        ->name('units.exercises.match-definition');

    Volt::route('units/{unit}/exercises/hangman', 'exercises.hangman')
        ->name('units.exercises.hangman');

    Volt::route('units/{unit}/exercises/complete-story', 'exercises.complete-story')
        ->name('units.exercises.complete-story');

    Volt::route('units/{unit}/exercises/write', 'exercises.write-dictation')
        ->name('units.exercises.write');

    Volt::route('units/{unit}/exercises/organize-definition', 'exercises.organize-definition')
        ->name('units.exercises.organize-definition');

    Volt::route('units/{unit}/exercises/organize-example', 'exercises.organize-example')
        ->name('units.exercises.organize-example');

    Volt::route('units/{unit}/exercises/read', 'exercises.read-flashcard')
        ->name('units.exercises.read');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
