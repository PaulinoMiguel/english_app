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
    public const MAX_WRONG = 6;
    public const EXERCISE_NUMBER = 5;

    public Unit $unit;

    public int $currentIndex = 0;

    /** @var array<string> uppercase letters guessed for current word */
    public array $guessed = [];

    public int $wrong = 0;

    public string $status = 'playing'; // playing | won | lost

    public int $won = 0;

    public int $lost = 0;

    public bool $finished = false;

    /** @var array<int> */
    public array $activeWordIds = [];

    /** @var array<int> Filtered word IDs shuffled at mount; persists across requests */
    public array $shuffledIds = [];

    public function mount(Unit $unit, WordMasteryService $masterySvc): void
    {
        $this->unit = $unit->load('book', 'words');
        $this->activeWordIds = $masterySvc->activeWordsForUnit(auth()->id(), $this->unit)->pluck('id')->all();

        $this->shuffledIds = $this->unit->words
            ->filter(fn ($w) => preg_match('/^[a-zA-Z]{3,}$/', $w->text) && in_array($w->id, $this->activeWordIds, true))
            ->pluck('id')
            ->shuffle()
            ->all();

        if (empty($this->shuffledIds)) {
            $this->finished = true;
            return;
        }

        $this->resetWord();
    }

    #[Computed]
    public function words(): Collection
    {
        if (empty($this->shuffledIds)) return collect();
        $byId = $this->unit->words->keyBy('id');
        return collect($this->shuffledIds)->map(fn ($id) => $byId->get($id))->filter()->values();
    }

    #[Computed]
    public function currentWord(): ?Word
    {
        return $this->words[$this->currentIndex] ?? null;
    }

    #[Computed]
    public function targetLetters(): array
    {
        $w = $this->currentWord;
        if (! $w) return [];
        return array_unique(str_split(strtoupper($w->text)));
    }

    #[Computed]
    public function displayLetters(): array
    {
        $w = $this->currentWord;
        if (! $w) return [];
        $chars = str_split(strtoupper($w->text));
        return array_map(fn ($c) => in_array($c, $this->guessed, true) ? $c : null, $chars);
    }

    #[Computed]
    public function isComplete(): bool
    {
        foreach ($this->targetLetters as $l) {
            if (! in_array($l, $this->guessed, true)) return false;
        }
        return true;
    }

    #[Computed]
    public function total(): int
    {
        return $this->words->count();
    }

    #[Computed]
    public function isLast(): bool
    {
        return $this->currentIndex >= $this->total - 1;
    }

    private function resetWord(): void
    {
        $this->guessed = [];
        $this->wrong = 0;
        $this->status = 'playing';
    }

    public function guess(string $letter, WordMasteryService $masterySvc): void
    {
        if ($this->status !== 'playing') return;

        $letter = strtoupper(substr($letter, 0, 1));
        if (! preg_match('/^[A-Z]$/', $letter)) return;
        if (in_array($letter, $this->guessed, true)) return;

        $this->guessed[] = $letter;

        // Recalculate target letters fresh (bypass Livewire computed cache)
        $w = $this->currentWord;
        $target = $w ? array_unique(str_split(strtoupper($w->text))) : [];

        if (in_array($letter, $target, true)) {
            // Inline completion check — don't trust the computed cache here
            $allGuessed = true;
            foreach ($target as $l) {
                if (! in_array($l, $this->guessed, true)) {
                    $allGuessed = false;
                    break;
                }
            }
            if ($allGuessed) {
                $this->status = 'won';
                $this->won++;
            }
        } else {
            $this->wrong++;
            if ($this->wrong >= self::MAX_WRONG) {
                $this->status = 'lost';
                $this->lost++;
                // Reveal all letters
                $this->guessed = array_unique(array_merge($this->guessed, $target));
                if ($w) {
                    $masterySvc->recordExerciseFault(auth()->id(), $this->unit, $w->id, self::EXERCISE_NUMBER);
                }
            }
        }
    }

    public function next(): void
    {
        if ($this->currentIndex < $this->total - 1) {
            $this->currentIndex++;
            $this->resetWord();
        } else {
            $this->finished = true;
        }
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 5);

        session()->flash('exercise_completed', '¡Ejercicio "Hangman" completado!');
        $this->redirect(route('units.show', $this->unit), navigate: true);
    }

    public function maskedClue(): string
    {
        $w = $this->currentWord;
        if (! $w) return '';

        $clue = $w->definition ?: $w->translation;
        if (! $clue) return '';

        if ($this->status !== 'playing') {
            return $clue; // reveal full clue when round is done
        }

        $target = $w->text;
        $mask = str_repeat('_', mb_strlen($target));

        return preg_replace('/\b'.preg_quote($target, '/').'\w*/iu', $mask, $clue);
    }

    public function letterClass(string $letter): string
    {
        $letter = strtoupper($letter);
        if (! in_array($letter, $this->guessed, true)) {
            return 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 border-gray-300 dark:border-gray-600 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer';
        }
        if (in_array($letter, $this->targetLetters, true)) {
            return 'bg-emerald-500 text-white border-emerald-500 cursor-default';
        }
        return 'bg-rose-200 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-700 cursor-default opacity-70';
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
                    Hangman · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($this->total === 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400">Esta unidad no tiene palabras válidas para Hangman.</p>
                    <a href="{{ route('units.show', $unit) }}" wire:navigate class="inline-block mt-4 text-sm font-medium {{ $palette['text'] }} hover:underline">
                        Volver a la unidad
                    </a>
                </div>
            @elseif ($finished)
                @php($total = $won + $lost)
                @php($score = $total > 0 ? (int) round(($won / $total) * 100) : 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500 text-white mb-4 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">¡Ejercicio terminado!</h3>
                    <div class="grid grid-cols-3 gap-3 max-w-md mx-auto my-6">
                        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $won }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Ganadas</div>
                        </div>
                        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800">
                            <div class="text-2xl font-bold text-rose-700 dark:text-rose-300">{{ $lost }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-rose-700/70 dark:text-rose-400/70">Perdidas</div>
                        </div>
                        <div class="p-3 rounded-lg {{ $palette['soft'] }} ring-1 ring-gray-200 dark:ring-gray-700">
                            <div class="text-2xl font-bold {{ $palette['text'] }}">{{ $score }}%</div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Puntaje</div>
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
                {{-- Progress + lives header --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Palabra {{ $currentIndex + 1 }} de {{ $this->total }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ {{ $won }}</span>
                            <span class="text-rose-600 dark:text-rose-400 font-semibold">✗ {{ $lost }}</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="{{ $palette['accent'] }} h-2 rounded-full transition-all"
                             style="width: {{ (($currentIndex + 1) / $this->total) * 100 }}%"></div>
                    </div>
                </div>

                <div wire:key="hm-{{ $word->id }}"
                     class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col min-h-[calc(100dvh-180px)] sm:min-h-0">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }} z-10"></div>
                    <div class="flex-1 overflow-y-auto p-5 sm:p-6 pb-2 sm:pb-6">

                        {{-- Lives display --}}
                        <div class="flex items-center justify-center gap-1.5 mb-5">
                            @for ($i = 0; $i < self::MAX_WRONG; $i++)
                                @if ($i < self::MAX_WRONG - $wrong)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-rose-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-300 dark:text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                            @endfor
                        </div>

                        {{-- Clue (with target word masked) --}}
                        <div class="text-center mb-5">
                            <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-2">Pista</p>
                            <p class="text-base sm:text-lg text-gray-800 dark:text-gray-200">
                                {{ $this->maskedClue() }}
                            </p>
                        </div>

                        {{-- Word display (underscores / letters) --}}
                        <div class="flex items-center justify-center gap-1.5 sm:gap-2 mb-6 flex-wrap">
                            @foreach ($this->displayLetters as $letter)
                                <div class="w-7 sm:w-9 h-10 sm:h-12 flex items-center justify-center border-b-2 {{ $status === 'lost' ? 'border-rose-400' : 'border-gray-400 dark:border-gray-500' }}">
                                    <span class="text-2xl sm:text-3xl font-bold {{ $status === 'won' ? 'text-emerald-600 dark:text-emerald-400' : ($status === 'lost' ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-gray-100') }}">
                                        {{ $letter ?? '' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Result message --}}
                        @if ($status === 'won')
                            <div class="text-center mb-5 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                                <p class="text-emerald-700 dark:text-emerald-300 font-semibold">¡Bien hecho! 🎉</p>
                                @if ($word->translation)
                                    <p class="text-sm text-emerald-700/80 dark:text-emerald-300/80 mt-1">{{ $word->translation }}</p>
                                @endif
                            </div>
                        @elseif ($status === 'lost')
                            <div class="text-center mb-5 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800">
                                <p class="text-rose-700 dark:text-rose-300 font-semibold">Se acabaron las vidas</p>
                                @if ($word->translation)
                                    <p class="text-sm text-rose-700/80 dark:text-rose-300/80 mt-1">La traducción es: {{ $word->translation }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- Audio button (always shown if word has audio, but more useful at end) --}}
                        @if ($word->audio_file && $status !== 'playing')
                            <div class="text-center mb-5" x-data="{ playing: false }">
                                <button type="button"
                                        @click="if (playing) { $refs.audio.pause(); $refs.audio.currentTime = 0; playing = false; } else { $refs.audio.play(); playing = true; }"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full {{ $palette['accent'] }} text-white text-sm font-medium hover:opacity-90 active:scale-95 transition shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                    </svg>
                                    <span x-text="playing ? 'Detener' : 'Escuchar palabra'">Escuchar palabra</span>
                                </button>
                                <audio x-ref="audio" @ended="playing = false" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->audio_file }}"></audio>
                            </div>
                        @endif

                    </div>

                    {{-- Keyboard / next button — anchored to bottom on mobile --}}
                    <div class="shrink-0 px-1 pt-2 pb-2 sm:px-6 sm:pt-0 sm:pb-6 border-t border-gray-100 dark:border-gray-700 sm:border-t-0">
                        @if ($status === 'playing')
                            <div class="space-y-1 sm:space-y-2">
                                @foreach (['QWERTYUIOP', 'ASDFGHJKL', 'ZXCVBNM'] as $row)
                                    <div class="flex justify-center gap-0.5 sm:gap-1.5">
                                        @foreach (str_split($row) as $letter)
                                            <button wire:click="guess('{{ $letter }}')"
                                                    @disabled(in_array($letter, $guessed, true))
                                                    class="flex-1 min-w-0 h-12 sm:h-16 rounded-md border-2 font-bold text-base sm:text-2xl transition-all {{ $this->letterClass($letter) }}">
                                                {{ $letter }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <button wire:click="next"
                                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                @if ($this->isLast)
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
