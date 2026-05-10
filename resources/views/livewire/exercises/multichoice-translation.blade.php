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
    public const EXERCISE_NUMBER = 3;

    public Unit $unit;

    public int $currentIndex = 0;

    public int $correct = 0;

    public int $wrong = 0;

    public ?int $selectedWordId = null;

    public bool $answered = false;

    public bool $finished = false;

    /** @var array<int, array<int>> */
    public array $optionsPerIndex = [];

    /** @var array<int> */
    public array $activeWordIds = [];

    /** @var array<int> Filtered word IDs shuffled at mount; persists across requests */
    public array $shuffledIds = [];

    public function mount(Unit $unit, WordMasteryService $masterySvc): void
    {
        $this->unit = $unit->load('book', 'words');
        $this->activeWordIds = $masterySvc->activeWordsForUnit(auth()->id(), $this->unit)->pluck('id')->all();

        $this->shuffledIds = $this->unit->words
            ->filter(fn ($w) => ! empty($w->translation) && in_array($w->id, $this->activeWordIds, true))
            ->pluck('id')
            ->shuffle()
            ->all();

        if (count($this->shuffledIds) < 2) {
            $this->finished = true;
            return;
        }

        $this->generateOptions();
    }

    private function generateOptions(): void
    {
        // Distractors come from any unit word with translation; questions only from active.
        $allWords = $this->unit->words->filter(fn ($w) => ! empty($w->translation))->pluck('id')->all();
        foreach ($this->words as $i => $word) {
            $distractors = collect($allWords)->reject(fn ($id) => $id === $word->id)->shuffle()->take(3)->values()->all();
            $options = collect([$word->id, ...$distractors])->shuffle()->all();
            $this->optionsPerIndex[$i] = $options;
        }
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
    public function options(): Collection
    {
        $word = $this->currentWord;
        if (! $word) return collect();
        $originalIndex = $this->unit->words->search(fn ($w) => $w->id === $word->id);
        $ids = $this->optionsPerIndex[$originalIndex] ?? [];
        $byId = $this->unit->words->keyBy('id');
        return collect($ids)->map(fn ($id) => $byId[$id] ?? null)->filter()->values();
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

    public function answer(int $wordId, WordMasteryService $masterySvc): void
    {
        if ($this->answered) return;

        $this->selectedWordId = $wordId;
        $this->answered = true;

        if ($wordId === $this->currentWord->id) {
            $this->correct++;
        } else {
            $this->wrong++;
            $masterySvc->recordExerciseFault(auth()->id(), $this->unit, $this->currentWord->id, self::EXERCISE_NUMBER);
        }
    }

    public function next(): void
    {
        if ($this->currentIndex < $this->total - 1) {
            $this->currentIndex++;
            $this->selectedWordId = null;
            $this->answered = false;
        } else {
            $this->finished = true;
        }
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 3);

        session()->flash('exercise_completed', '¡Ejercicio "Multichoice in Translation" completado!');
        $this->redirect(route('units.show', $this->unit), navigate: true);
    }

    public function optionClass(int $optId): string
    {
        if (! $this->answered) {
            return 'border-gray-300 dark:border-gray-600 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-gray-800 dark:text-gray-200';
        }
        if ($optId === $this->currentWord->id) {
            return 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200';
        }
        if ($optId === $this->selectedWordId) {
            return 'border-rose-500 bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200';
        }
        return 'border-gray-200 dark:border-gray-700 opacity-50 text-gray-600 dark:text-gray-400';
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
                    Multichoice Translation · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($finished)
                @php($score = $this->total > 0 ? (int) round(($correct / $this->total) * 100) : 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500 text-white mb-4 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">¡Ejercicio terminado!</h3>
                    <div class="grid grid-cols-3 gap-3 max-w-md mx-auto my-6">
                        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $correct }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Correctas</div>
                        </div>
                        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800">
                            <div class="text-2xl font-bold text-rose-700 dark:text-rose-300">{{ $wrong }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-rose-700/70 dark:text-rose-400/70">Incorrectas</div>
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
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">Pregunta {{ $currentIndex + 1 }} de {{ $this->total }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ {{ $correct }}</span>
                            <span class="text-rose-600 dark:text-rose-400 font-semibold">✗ {{ $wrong }}</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="{{ $palette['accent'] }} h-2 rounded-full transition-all"
                             style="width: {{ (($currentIndex + 1) / $this->total) * 100 }}%"></div>
                    </div>
                </div>

                <div wire:key="mct-{{ $word->id }}"
                     class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>
                    <div class="p-6 sm:p-8">
                        <div class="text-center mb-6">
                            <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-2">¿Cuál es la traducción de?</p>
                            <p class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100 mb-1">{{ $word->text }}</p>
                            @if ($word->phonetic)
                                <p class="text-sm italic text-gray-500 dark:text-gray-400">/{{ $word->phonetic }}/</p>
                            @endif
                            @if ($word->audio_file)
                                <div x-data="{ playing: false }" class="mt-3">
                                    <button type="button"
                                            @click="if (playing) { $refs.audio.pause(); $refs.audio.currentTime = 0; playing = false; } else { $refs.audio.play(); playing = true; }"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-full {{ $palette['accent'] }} text-white hover:opacity-90 active:scale-95 transition shadow-sm"
                                            aria-label="Reproducir {{ $word->text }}">
                                        <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                        </svg>
                                        <svg x-show="playing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                        </svg>
                                        <audio x-ref="audio" @ended="playing = false" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->audio_file }}"></audio>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            @foreach ($this->options as $opt)
                                <button wire:click="answer({{ $opt->id }})"
                                        @disabled($answered)
                                        class="px-4 py-3 rounded-lg border-2 font-medium text-base text-left transition-all {{ $this->optionClass($opt->id) }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span>{{ $opt->translation }}</span>
                                        @if ($answered && $opt->id === $word->id)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 h-5 w-5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        @elseif ($answered && $opt->id === $selectedWordId)
                                            <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 h-5 w-5 text-rose-600 dark:text-rose-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        @if ($answered)
                            <button wire:click="next"
                                    x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                                    class="mt-5 w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                @if ($this->isLast)
                                    Ver resultados
                                @else
                                    Siguiente pregunta
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
