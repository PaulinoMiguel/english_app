<?php

use App\Models\Unit;
use App\Models\Word;
use App\Services\ProgressService;
use App\Services\WordMasteryService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public const EXERCISE_NUMBER = 7;

    public Unit $unit;

    /** @var array<int> Word IDs in processing order; failed words get appended */
    public array $queue = [];

    public int $position = 0;

    public int $totalUnique = 0;

    /** @var array<int> Word IDs already answered correctly */
    public array $correctIds = [];

    public string $userInput = '';

    public bool $answered = false;

    public bool $isCorrect = false;

    public int $correct = 0;

    public int $wrong = 0;

    public bool $finished = false;

    /** @var array<int> */
    public array $activeWordIds = [];

    public function mount(Unit $unit, WordMasteryService $masterySvc): void
    {
        $this->unit = $unit->load('book', 'words');
        $this->activeWordIds = $masterySvc->activeWordsForUnit(auth()->id(), $this->unit)->pluck('id')->all();

        if ($this->words->isEmpty()) {
            $this->finished = true;
            return;
        }

        $this->queue = $this->words->pluck('id')->all();
        shuffle($this->queue);
        $this->totalUnique = count($this->queue);
    }

    #[Computed]
    public function words(): Collection
    {
        return $this->unit->words
            ->filter(fn ($w) => ! empty($w->audio_file) && in_array($w->id, $this->activeWordIds, true))
            ->values();
    }

    #[Computed]
    public function currentWord(): ?Word
    {
        $id = $this->queue[$this->position] ?? null;
        if ($id === null) return null;
        return $this->unit->words->firstWhere('id', $id);
    }

    #[Computed]
    public function masteredCount(): int
    {
        return count($this->correctIds);
    }

    #[Computed]
    public function remaining(): int
    {
        return $this->totalUnique - $this->masteredCount;
    }

    #[Computed]
    public function progressPercent(): int
    {
        if ($this->totalUnique === 0) return 0;
        return (int) round(($this->masteredCount / $this->totalUnique) * 100);
    }

    public function addLetter(string $letter): void
    {
        if ($this->answered) return;
        $letter = substr($letter, 0, 1);
        if ($letter === '' || ! preg_match('/^[a-zA-Z]$/', $letter)) return;
        $this->userInput .= strtolower($letter);
    }

    public function backspace(): void
    {
        if ($this->answered) return;
        $this->userInput = mb_substr($this->userInput, 0, max(0, mb_strlen($this->userInput) - 1));
    }

    public function clearInput(): void
    {
        if ($this->answered) return;
        $this->userInput = '';
    }

    public function submit(WordMasteryService $masterySvc): void
    {
        if ($this->answered) return;

        $w = $this->currentWord;
        if (! $w) return;

        $expected = mb_strtolower(trim($w->text));
        $given = mb_strtolower(trim($this->userInput));

        $this->isCorrect = $expected !== '' && $expected === $given;
        $this->answered = true;

        if ($this->isCorrect) {
            $this->correct++;
            if (! in_array($w->id, $this->correctIds, true)) {
                $this->correctIds[] = $w->id;
            }
        } else {
            $this->wrong++;
            $masterySvc->recordExerciseFault(auth()->id(), $this->unit, $w->id, self::EXERCISE_NUMBER);
            // Re-queue the word so the user has to retry it later
            $this->queue[] = $w->id;
        }
    }

    public function next(): void
    {
        $this->position++;
        $this->userInput = '';
        $this->answered = false;
        $this->isCorrect = false;

        if ($this->position >= count($this->queue)) {
            $this->finished = true;
        }
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 7);

        session()->flash('exercise_completed', '¡Ejercicio "Write" completado!');
        $this->redirect(route('units.show', $this->unit), navigate: true);
    }

    public function with(): array
    {
        $palette = match($this->unit->book->level) {
            'Beginner' => ['accent' => 'bg-emerald-500', 'badge' => 'bg-emerald-500 text-white', 'text' => 'text-emerald-600 dark:text-emerald-400', 'soft' => 'bg-emerald-50 dark:bg-emerald-900/30'],
            'Elementary' => ['accent' => 'bg-teal-500', 'badge' => 'bg-teal-500 text-white', 'text' => 'text-teal-600 dark:text-teal-400', 'soft' => 'bg-teal-50 dark:bg-teal-900/30'],
            'Pre-Intermediate' => ['accent' => 'bg-cyan-500', 'badge' => 'bg-cyan-500 text-white', 'text' => 'text-cyan-600 dark:text-cyan-400', 'soft' => 'bg-cyan-50 dark:bg-cyan-900/30'],
            'Intermediate' => ['accent' => 'bg-blue-500', 'badge' => 'bg-blue-500 text-white', 'text' => 'text-blue-600 dark:text-blue-400', 'soft' => 'bg-blue-50 dark:bg-blue-900/30'],
            'Upper-Intermediate' => ['accent' => 'bg-indigo-500', 'badge' => 'bg-indigo-500 text-white', 'text' => 'text-indigo-600 dark:text-indigo-400', 'soft' => 'bg-indigo-50 dark:bg-indigo-900/30'],
            'Advanced' => ['accent' => 'bg-purple-500', 'badge' => 'bg-purple-500 text-white', 'text' => 'text-purple-600 dark:text-purple-400', 'soft' => 'bg-purple-50 dark:bg-purple-900/30'],
            default => ['accent' => 'bg-gray-500', 'badge' => 'bg-gray-500 text-white', 'text' => 'text-gray-600 dark:text-gray-400', 'soft' => 'bg-gray-50 dark:bg-gray-700/50'],
        };

        return ['palette' => $palette];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('units.show', $unit) }}"
               wire:navigate
               class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition"
               aria-label="Volver">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
                    Write · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($totalUnique === 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400">Esta unidad no tiene palabras con audio.</p>
                    <a href="{{ route('units.show', $unit) }}" wire:navigate class="inline-block mt-4 text-sm font-medium {{ $palette['text'] }} hover:underline">
                        Volver a la unidad
                    </a>
                </div>
            @elseif ($finished)
                @php($totalAttempts = $correct + $wrong)
                @php($firstTryRate = $totalAttempts > 0 ? (int) round(($totalUnique / $totalAttempts) * 100) : 100)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500 text-white mb-4 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">¡Dominaste todas las palabras!</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Acertaste las {{ $totalUnique }} palabras de la unidad.</p>
                    <div class="grid grid-cols-3 gap-3 max-w-md mx-auto my-6">
                        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $correct }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Aciertos</div>
                        </div>
                        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800">
                            <div class="text-2xl font-bold text-rose-700 dark:text-rose-300">{{ $wrong }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-rose-700/70 dark:text-rose-400/70">Fallos</div>
                        </div>
                        <div class="p-3 rounded-lg {{ $palette['soft'] }} ring-1 ring-gray-200 dark:ring-gray-700">
                            <div class="text-2xl font-bold {{ $palette['text'] }}">{{ $firstTryRate }}%</div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Eficiencia</div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <button wire:click="complete"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg bg-emerald-500 text-white font-semibold hover:bg-emerald-600 active:scale-95 transition disabled:opacity-50">
                            Marcar como completado
                        </button>
                        <a href="{{ route('units.show', $unit) }}" wire:navigate
                           class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Volver sin guardar
                        </a>
                    </div>
                </div>
            @else
                @php($word = $this->currentWord)
                {{-- Progress + score header --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            {{ $this->masteredCount }} / {{ $totalUnique }} dominadas
                            @if ($this->remaining > 0)
                                · <span class="text-amber-600 dark:text-amber-400">faltan {{ $this->remaining }}</span>
                            @endif
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ {{ $correct }}</span>
                            <span class="text-rose-600 dark:text-rose-400 font-semibold">✗ {{ $wrong }}</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="{{ $palette['accent'] }} h-2 rounded-full transition-all"
                             style="width: {{ $this->progressPercent }}%"></div>
                    </div>
                </div>

                <div wire:key="write-{{ $word->id }}"
                     x-data="{ playing: false }"
                     class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>
                    <div class="p-6 sm:p-8">

                        <div class="text-center mb-6">
                            <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-3">Escucha y escribe la palabra</p>

                            <button type="button"
                                    @click="if ($refs.audio.paused) { $refs.audio.play().catch(() => {}); } else { $refs.audio.pause(); $refs.audio.currentTime = 0; }"
                                    class="inline-flex items-center gap-2 px-6 py-3 rounded-full {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                </svg>
                                <svg x-show="playing" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                </svg>
                                <span x-text="playing ? 'Detener' : 'Reproducir audio'">Reproducir audio</span>
                            </button>
                            <audio wire:key="audio-{{ $word->id }}"
                                   wire:ignore
                                   x-ref="audio"
                                   x-init="$nextTick(() => $el.play().catch(() => {}))"
                                   @play="playing = true"
                                   @pause="playing = false"
                                   @ended="playing = false"
                                   preload="auto"
                                   src="{{ config('app.audio_base_url') }}/{{ $word->audio_file }}"></audio>

                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">Puedes reproducirlo cuantas veces quieras.</p>
                        </div>

                        {{-- Display of current input --}}
                        <div class="space-y-3"
                            @if (! $answered)
                                x-data
                                @keydown.window="
                                    if ($event.target.tagName === 'INPUT' || $event.target.tagName === 'TEXTAREA') return;
                                    if (/^[a-zA-Z]$/.test($event.key)) { $wire.addLetter($event.key); $event.preventDefault(); }
                                    else if ($event.key === 'Backspace') { $wire.backspace(); $event.preventDefault(); }
                                    else if ($event.key === 'Enter') { $wire.submit(); $event.preventDefault(); }
                                "
                            @endif
                        >
                            <p class="text-xs uppercase tracking-wide font-semibold text-gray-600 dark:text-gray-300">Tu respuesta</p>
                            <div class="w-full min-h-[58px] px-4 py-3 rounded-lg border-2 text-2xl font-bold tracking-wider text-center transition-all
                                @if ($answered)
                                    @if ($isCorrect)
                                        border-emerald-500 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200
                                    @else
                                        border-rose-500 bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200
                                    @endif
                                @else
                                    border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100
                                @endif">
                                @if ($userInput === '')
                                    <span class="text-base font-normal text-gray-400 dark:text-gray-500 italic">Toca las letras de abajo…</span>
                                @else
                                    {{ $userInput }}@if (! $answered)<span class="inline-block w-0.5 h-6 bg-gray-700 dark:bg-gray-200 align-middle ml-0.5 animate-pulse"></span>@endif
                                @endif
                            </div>
                        </div>

                        {{-- Custom QWERTY keyboard (WhatsApp-style) --}}
                        @if (! $answered)
                            <div class="-mx-5 sm:mx-0 px-1 sm:px-0 mt-4 space-y-1 sm:space-y-1.5">
                                {{-- Row 1: qwertyuiop --}}
                                <div class="flex justify-center gap-0.5 sm:gap-1">
                                    @foreach (str_split('qwertyuiop') as $letter)
                                        <button type="button"
                                                wire:click="addLetter('{{ $letter }}')"
                                                class="flex-1 min-w-0 h-12 sm:h-14 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 font-bold text-base sm:text-lg active:bg-indigo-100 dark:active:bg-indigo-900/40 active:scale-95 transition shadow-sm">
                                            {{ strtoupper($letter) }}
                                        </button>
                                    @endforeach
                                </div>
                                {{-- Row 2: asdfghjkl --}}
                                <div class="flex justify-center gap-0.5 sm:gap-1">
                                    @foreach (str_split('asdfghjkl') as $letter)
                                        <button type="button"
                                                wire:click="addLetter('{{ $letter }}')"
                                                class="flex-1 min-w-0 h-12 sm:h-14 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 font-bold text-base sm:text-lg active:bg-indigo-100 dark:active:bg-indigo-900/40 active:scale-95 transition shadow-sm">
                                            {{ strtoupper($letter) }}
                                        </button>
                                    @endforeach
                                </div>
                                {{-- Row 3: zxcvbnm + backspace --}}
                                <div class="flex justify-center gap-0.5 sm:gap-1">
                                    @foreach (str_split('zxcvbnm') as $letter)
                                        <button type="button"
                                                wire:click="addLetter('{{ $letter }}')"
                                                class="flex-1 min-w-0 h-12 sm:h-14 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 font-bold text-base sm:text-lg active:bg-indigo-100 dark:active:bg-indigo-900/40 active:scale-95 transition shadow-sm">
                                            {{ strtoupper($letter) }}
                                        </button>
                                    @endforeach
                                    <button type="button"
                                            wire:click="backspace"
                                            @disabled($userInput === '')
                                            aria-label="Borrar letra"
                                            class="flex-[1.5] min-w-0 h-12 sm:h-14 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-200 active:bg-rose-100 dark:active:bg-rose-900/40 active:scale-95 transition shadow-sm flex items-center justify-center disabled:opacity-40">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/>
                                            <line x1="18" y1="9" x2="12" y2="15"/>
                                            <line x1="12" y1="9" x2="18" y2="15"/>
                                        </svg>
                                    </button>
                                </div>
                                {{-- Row 4: shift (no-op) + space (no-op) + enter (submit) --}}
                                <div class="flex justify-center gap-0.5 sm:gap-1">
                                    <button type="button"
                                            disabled
                                            aria-hidden="true"
                                            tabindex="-1"
                                            class="flex-[2] min-w-0 h-12 sm:h-14 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400 shadow-sm flex items-center justify-center cursor-default opacity-70">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="18 15 12 9 6 15"/>
                                        </svg>
                                    </button>
                                    <button type="button"
                                            disabled
                                            aria-hidden="true"
                                            tabindex="-1"
                                            class="flex-[3] min-w-0 h-12 sm:h-14 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-400 dark:text-gray-500 shadow-sm cursor-default opacity-70">
                                    </button>
                                    <button type="button"
                                            wire:click="submit"
                                            @disabled(trim($userInput) === '')
                                            aria-label="Comprobar"
                                            class="flex-[2] min-w-0 h-12 sm:h-14 rounded-md font-bold text-sm shadow-sm transition active:scale-95 flex items-center justify-center gap-1 {{ trim($userInput) === '' ? 'bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed' : $palette['accent'].' text-white hover:opacity-90' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="9 10 4 15 9 20"/>
                                            <path d="M20 4v7a4 4 0 0 1-4 4H4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if ($answered)
                            @if ($isCorrect)
                                <div class="mt-5 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800 text-center">
                                    <p class="text-emerald-700 dark:text-emerald-300 font-semibold flex items-center justify-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        ¡Correcto!
                                    </p>
                                </div>
                            @else
                                <div class="mt-5 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800 text-center">
                                    <p class="text-rose-700 dark:text-rose-300 font-semibold mb-1">Incorrecto</p>
                                    <p class="text-sm text-rose-700/80 dark:text-rose-300/80">La palabra es: <span class="font-bold">{{ $word->text }}</span></p>
                                </div>
                            @endif

                            @if ($word->translation)
                                <div class="mt-3 p-3 rounded-lg {{ $palette['soft'] }}">
                                    <p class="text-xs uppercase tracking-wide font-bold {{ $palette['text'] }} mb-1">Traducción</p>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $word->translation }}</p>
                                </div>
                            @endif

                            <button wire:click="next"
                                    x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                                    class="mt-5 w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                @if (($position >= count($queue) - 1) && $isCorrect)
                                    Ver resultados
                                @elseif (! $isCorrect)
                                    Siguiente (vuelve al final)
                                @else
                                    Siguiente palabra
                                @endif
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
