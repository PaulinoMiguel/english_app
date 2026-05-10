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
    public const EXERCISE_NUMBER = 9;

    public Unit $unit;

    /** @var array<int> Word IDs in queue (failed words re-queued) */
    public array $queue = [];

    public int $position = 0;

    public int $totalUnique = 0;

    /** @var array<int> Word IDs already mastered */
    public array $correctIds = [];

    /** @var array<int> Token indices in order placed by user */
    public array $placedIndices = [];

    /** @var array<int> Shuffled token indices for bank display */
    public array $shuffledOrder = [];

    public bool $answered = false;

    public bool $isCorrect = false;

    public bool $wrongAttempt = false;

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
        $this->totalUnique = count($this->queue);
        $this->shuffleCurrent();
    }

    #[Computed]
    public function words(): Collection
    {
        return $this->unit->words->filter(function ($w) {
            if (empty($w->example)) return false;
            if (! in_array($w->id, $this->activeWordIds, true)) return false;
            $count = count($this->tokenize($w->example));
            return $count >= 3 && $count <= 14;
        })->values();
    }

    #[Computed]
    public function currentWord(): ?Word
    {
        $id = $this->queue[$this->position] ?? null;
        if ($id === null) return null;
        return $this->unit->words->firstWhere('id', $id);
    }

    #[Computed]
    public function tokens(): array
    {
        $w = $this->currentWord;
        if (! $w) return [];
        return $this->tokenize($w->example);
    }

    private function tokenize(string $sentence): array
    {
        $words = array_values(array_filter(preg_split('/\s+/u', trim($sentence)), fn ($t) => $t !== ''));

        // Greedy: agrupa palabras adyacentes mientras el total (con espacios) no supere 9 chars.
        // Asi "the ia is" queda como un solo bloque en vez de tres botones diminutos.
        $groups = [];
        $i = 0;
        $n = count($words);
        while ($i < $n) {
            $group = $words[$i];
            while ($i + 1 < $n && mb_strlen($group) + 1 + mb_strlen($words[$i + 1]) <= 9) {
                $i++;
                $group .= ' ' . $words[$i];
            }
            $groups[] = $group;
            $i++;
        }
        return $groups;
    }

    private function shuffleCurrent(): void
    {
        $count = count($this->tokens);
        if ($count === 0) {
            $this->shuffledOrder = [];
            return;
        }

        $indices = range(0, $count - 1);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            shuffle($indices);
            if ($indices !== range(0, $count - 1)) {
                $this->shuffledOrder = $indices;
                return;
            }
        }
        $this->shuffledOrder = $indices;
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

    public function placeToken(int $tokenIndex, WordMasteryService $masterySvc): void
    {
        if ($this->answered) return;
        if (! isset($this->tokens[$tokenIndex])) return;
        if (in_array($tokenIndex, $this->placedIndices, true)) return;

        $this->placedIndices[] = $tokenIndex;

        if (count($this->placedIndices) === count($this->tokens)) {
            $this->check($masterySvc);
        }
    }

    public function removeToken(int $position): void
    {
        if ($this->answered) return;
        if (! isset($this->placedIndices[$position])) return;

        array_splice($this->placedIndices, $position, 1);
        $this->wrongAttempt = false;
    }

    public function clearAnswer(): void
    {
        if ($this->answered) return;
        $this->placedIndices = [];
        $this->wrongAttempt = false;
    }

    private function check(WordMasteryService $masterySvc): void
    {
        // Validate by exact text — duplicate identical tokens are interchangeable, but case matters
        $tokens = $this->tokens;
        $placedText = array_map(fn ($idx) => $tokens[$idx] ?? '', $this->placedIndices);
        $expectedText = array_values($tokens);
        $isCorrect = $placedText === $expectedText;

        $w = $this->currentWord;
        if ($isCorrect) {
            $this->isCorrect = true;
            $this->answered = true;
            $this->wrongAttempt = false;
            $this->correct++;
            if ($w && ! in_array($w->id, $this->correctIds, true)) {
                $this->correctIds[] = $w->id;
            }
        } else {
            // Don't lock — user can fix the answer and try again
            $this->wrongAttempt = true;
            $this->wrong++;
            if ($w) {
                $masterySvc->recordExerciseFault(auth()->id(), $this->unit, $w->id, self::EXERCISE_NUMBER);
            }
        }
    }

    public function skip(): void
    {
        if ($this->answered) return;
        $w = $this->currentWord;
        if ($w) {
            $this->queue[] = $w->id;
        }
        $this->advance();
    }

    public function next(): void
    {
        if (! $this->answered) return;
        $this->advance();
    }

    private function advance(): void
    {
        $this->position++;
        $this->placedIndices = [];
        $this->answered = false;
        $this->isCorrect = false;
        $this->wrongAttempt = false;
        $this->shuffleCurrent();

        if ($this->position >= count($this->queue)) {
            $this->finished = true;
        }
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 9);

        session()->flash('exercise_completed', '¡Ejercicio "Organize Example" completado!');
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
                    Organize Example · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($totalUnique === 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400">Esta unidad no tiene ejemplos aptos para este ejercicio.</p>
                    <a href="{{ route('units.show', $unit) }}" wire:navigate class="inline-block mt-4 text-sm font-medium {{ $palette['text'] }} hover:underline">
                        Volver a la unidad
                    </a>
                </div>
            @elseif ($finished)
                @php
                    $totalAttempts = $correct + $wrong;
                    $firstTryRate = $totalAttempts > 0 ? (int) round(($totalUnique / $totalAttempts) * 100) : 100;
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500 text-white mb-4 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">¡Dominaste todos los ejemplos!</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Reordenaste correctamente los {{ $totalUnique }} ejemplos.</p>
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
                @php
                    $word = $this->currentWord;
                @endphp
                {{-- Progress header --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            {{ $this->masteredCount }} / {{ $totalUnique }} dominados
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

                <div wire:key="org-ex-{{ $word->id }}-{{ $position }}"
                     class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>
                    <div class="p-5 sm:p-6">

                        {{-- Hint: word + audio of example --}}
                        <div class="text-center mb-5">
                            <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-2">Ordena el ejemplo de</p>
                            <div class="inline-flex items-center gap-2">
                                <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $word->text }}</span>
                            </div>
                            @if ($word->translation)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $word->translation }}</p>
                            @endif

                            @if ($word->example_audio)
                                <div x-data="{ playing: false }"
                                     x-init="$nextTick(() => { $refs.audio?.play().then(() => playing = true).catch(() => {}); })"
                                     class="mt-3">
                                    <button type="button"
                                            @click="if (playing) { $refs.audio.pause(); $refs.audio.currentTime = 0; playing = false; } else { $refs.audio.play(); playing = true; }"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-full {{ $palette['accent'] }} text-white text-sm font-medium hover:opacity-90 active:scale-95 transition shadow-sm">
                                        <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                        </svg>
                                        <svg x-show="playing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                        </svg>
                                        <span x-text="playing ? 'Detener' : 'Escuchar ejemplo'">Escuchar ejemplo</span>
                                    </button>
                                    <audio x-ref="audio" @ended="playing = false" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->example_audio }}"></audio>
                                </div>
                            @endif
                        </div>

                        {{-- Answer area: tokens placed in order --}}
                        @php
                            if ($answered && $isCorrect) {
                                $answerBorder = 'border-emerald-400 bg-emerald-50 dark:bg-emerald-900/20';
                            } elseif ($wrongAttempt) {
                                $answerBorder = 'border-rose-400 bg-rose-50 dark:bg-rose-900/20';
                            } else {
                                $answerBorder = 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50';
                            }
                        @endphp
                        <div class="min-h-[80px] p-3 rounded-lg border-2 border-dashed {{ $answerBorder }} flex flex-wrap gap-2 items-start mb-2">
                            @if (count($placedIndices) === 0)
                                <p class="w-full text-center text-sm text-gray-400 dark:text-gray-500 italic self-center">
                                    Toca las palabras de abajo para construir la oración…
                                </p>
                            @else
                                @foreach ($placedIndices as $pos => $tokenIndex)
                                    <button wire:click="removeToken({{ $pos }})"
                                            @disabled($answered)
                                            class="px-3 py-1.5 rounded-md font-medium text-sm transition {{ $answered ? 'bg-emerald-200 dark:bg-emerald-800 text-emerald-900 dark:text-emerald-100 cursor-default' : ($wrongAttempt ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-900 dark:text-rose-100 ring-1 ring-rose-300 dark:ring-rose-700 hover:bg-rose-200 dark:hover:bg-rose-900/60 cursor-pointer' : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 ring-1 ring-gray-300 dark:ring-gray-600 hover:bg-rose-50 hover:ring-rose-400 dark:hover:bg-rose-900/20') }}">
                                        {{ $this->tokens[$tokenIndex] ?? '' }}
                                    </button>
                                @endforeach
                            @endif
                        </div>

                        @if (! $answered && count($placedIndices) > 0)
                            <div class="text-right mb-3">
                                <button wire:click="clearAnswer" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline">
                                    Limpiar
                                </button>
                            </div>
                        @endif

                        {{-- Bank of remaining tokens --}}
                        @if (! $answered)
                            <p class="text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400 mb-2">Palabras disponibles</p>
                            <div class="flex flex-wrap gap-2 mb-2">
                                @foreach ($shuffledOrder as $tokenIndex)
                                    @php
                                        $used = in_array($tokenIndex, $placedIndices, true);
                                    @endphp
                                    <button wire:click="placeToken({{ $tokenIndex }})"
                                            @disabled($used)
                                            class="px-3 py-1.5 rounded-md font-medium text-sm transition {{ $used ? 'bg-gray-100 dark:bg-gray-700/50 text-gray-300 dark:text-gray-600 line-through cursor-not-allowed' : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 ring-1 ring-gray-300 dark:ring-gray-600 hover:ring-indigo-400 dark:hover:ring-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer active:scale-95' }}">
                                        {{ $this->tokens[$tokenIndex] ?? '' }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        {{-- Wrong attempt: editable error --}}
                        @if ($wrongAttempt && ! $answered)
                            <div class="mt-4 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800 text-center">
                                <p class="text-rose-700 dark:text-rose-300 font-semibold flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                    No es correcto
                                </p>
                                <p class="text-xs text-rose-600/80 dark:text-rose-400/80 mt-1">Quita las palabras y vuelve a ordenarlas. O usa "Saltar" si quieres pasar a la siguiente.</p>
                            </div>

                            <button wire:click="skip"
                                    class="mt-3 w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg border-2 border-rose-300 dark:border-rose-700 text-rose-700 dark:text-rose-300 font-medium hover:bg-rose-50 dark:hover:bg-rose-900/20 transition">
                                Saltar (vuelve al final)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        @endif

                        {{-- Correct: show success + Next button --}}
                        @if ($answered && $isCorrect)
                            <div class="mt-4 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800 text-center">
                                <p class="text-emerald-700 dark:text-emerald-300 font-semibold flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    ¡Correcto!
                                </p>
                            </div>

                            <button wire:click="next"
                                    x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                                    class="mt-4 w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                @if ($position >= count($queue) - 1)
                                    Ver resultados
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
