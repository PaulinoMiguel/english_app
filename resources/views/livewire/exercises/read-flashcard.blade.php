<?php

use App\Models\Unit;
use App\Models\Word;
use App\Services\ProgressService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Unit $unit;

    /** @var array<int> Word IDs in queue order; failed cards get re-queued */
    public array $queue = [];

    public int $position = 0;

    public int $totalUnique = 0;

    /** @var array<int> Word IDs already known */
    public array $knownIds = [];

    public bool $revealed = false;

    public int $correct = 0;

    public int $wrong = 0;

    public bool $finished = false;

    public function mount(Unit $unit): void
    {
        $this->unit = $unit->load('book', 'words');

        if ($this->words->isEmpty()) {
            $this->finished = true;
            return;
        }

        $this->queue = $this->words->pluck('id')->all();
        $this->totalUnique = count($this->queue);
    }

    #[Computed]
    public function words(): Collection
    {
        // Only words with a definition (we mask it as the riddle)
        return $this->unit->words->filter(fn ($w) => ! empty($w->definition))->values();
    }

    #[Computed]
    public function currentWord(): ?Word
    {
        $id = $this->queue[$this->position] ?? null;
        if ($id === null) return null;
        return $this->unit->words->firstWhere('id', $id);
    }

    public function maskedDefinition(): string
    {
        $w = $this->currentWord;
        if (! $w || empty($w->definition)) return '';

        $mask = str_repeat('_', mb_strlen($w->text));
        return preg_replace('/\b'.preg_quote($w->text, '/').'\w*/iu', $mask, $w->definition);
    }

    #[Computed]
    public function knownCount(): int
    {
        return count($this->knownIds);
    }

    #[Computed]
    public function remaining(): int
    {
        return $this->totalUnique - $this->knownCount;
    }

    #[Computed]
    public function progressPercent(): int
    {
        if ($this->totalUnique === 0) return 0;
        return (int) round(($this->knownCount / $this->totalUnique) * 100);
    }

    public function flip(): void
    {
        $this->revealed = ! $this->revealed;
    }

    public function previous(): void
    {
        if ($this->position > 0) {
            $this->position--;
            $this->revealed = false;
            unset($this->currentWord);
        }
    }

    public function knewIt(): void
    {
        if (! $this->revealed) return;
        $w = $this->currentWord;
        if ($w && ! in_array($w->id, $this->knownIds, true)) {
            $this->knownIds[] = $w->id;
            $this->correct++;
        }
        $this->advance();
    }

    public function didntKnow(): void
    {
        if (! $this->revealed) return;
        $w = $this->currentWord;
        if ($w) {
            $this->wrong++;
            $this->queue[] = $w->id; // re-queue at the end
        }
        $this->advance();
    }

    private function advance(): void
    {
        $this->position++;
        $this->revealed = false;

        // Bust Livewire computed cache so currentWord re-evaluates with new position
        unset($this->currentWord);

        if ($this->position >= count($this->queue)) {
            $this->finished = true;
        }
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 8);

        session()->flash('exercise_completed', '¡Ejercicio "Read" completado!');
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
                    Read · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            @if ($totalUnique === 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400">Esta unidad no tiene definiciones disponibles.</p>
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
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">¡Te las sabes todas!</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Reconociste por su definición las {{ $totalUnique }} palabras de la unidad. Ya estás listo para Hangman.</p>

                    <div class="grid grid-cols-3 gap-3 max-w-md mx-auto my-6">
                        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $correct }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Sabidas</div>
                        </div>
                        <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800">
                            <div class="text-2xl font-bold text-rose-700 dark:text-rose-300">{{ $wrong }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-rose-700/70 dark:text-rose-400/70">No sabidas</div>
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
                            Marcar ejercicio como completado
                        </button>
                        <a href="{{ route('units.show', $unit) }}" wire:navigate
                           class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Volver sin guardar
                        </a>
                    </div>
                </div>
            @else
                @php($word = $this->currentWord)

                {{-- Progress header --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            {{ $this->knownCount }} / {{ $totalUnique }} sabidas
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

                <div wire:key="read-{{ $word->id }}-{{ $position }}"
                     class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden min-h-[420px]">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>

                    @if (! $revealed)
                        {{-- FRONT: masked definition --}}
                        <div class="p-6 sm:p-8 flex flex-col justify-center min-h-[420px]">
                            <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-4 text-center">¿Qué palabra es?</p>

                            <div class="rounded-lg {{ $palette['soft'] }} p-5 sm:p-6 mb-6">
                                <p class="text-lg sm:text-xl leading-relaxed text-gray-800 dark:text-gray-200 text-center">
                                    {{ $this->maskedDefinition() }}
                                </p>
                            </div>

                            @if ($word->type)
                                <p class="text-center mb-6">
                                    <span class="text-xs font-medium px-2 py-1 rounded {{ $palette['soft'] }} {{ $palette['text'] }}">
                                        {{ $word->type }}
                                    </span>
                                </p>
                            @endif

                            <button wire:click="flip"
                                    class="w-full sm:w-auto sm:mx-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                </svg>
                                Mostrar palabra
                            </button>
                        </div>
                    @else
                        {{-- BACK: word revealed --}}
                        <div class="p-6 sm:p-8 min-h-[420px]">
                            <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-3 text-center">La palabra es</p>

                            <div class="text-center mb-5">
                                <div class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                    {{ $word->text }}
                                </div>
                                @if ($word->phonetic)
                                    <p class="text-sm italic text-gray-500 dark:text-gray-400">/{{ $word->phonetic }}/</p>
                                @endif
                            </div>

                            @if ($word->translation)
                                <div class="mb-4 p-3 rounded-lg {{ $palette['soft'] }}">
                                    <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-1">Traducción</p>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $word->translation }}</p>
                                </div>
                            @endif

                            @if ($word->definition)
                                <div class="mb-4">
                                    <p class="text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400 mb-1">Definition</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $word->definition }}</p>
                                </div>
                            @endif

                            @if ($word->example)
                                <div class="mb-4">
                                    <p class="text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400 mb-1">Example</p>
                                    <p class="text-sm italic text-gray-700 dark:text-gray-300">"{{ $word->example }}"</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Action buttons --}}
                @if ($revealed)
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <button wire:click="didntKnow"
                                class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-rose-500 text-white font-semibold hover:bg-rose-600 active:scale-95 transition shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                            No la sabía
                        </button>

                        <button wire:click="knewIt"
                                class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-emerald-500 text-white font-semibold hover:bg-emerald-600 active:scale-95 transition shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            La sabía
                        </button>
                    </div>
                @else
                    <div class="flex items-center justify-between gap-3 mt-5">
                        <button wire:click="previous"
                                @disabled($position === 0)
                                class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Anterior
                        </button>

                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center px-3 italic">
                            Lee la pista, piensa la palabra, luego revela
                        </p>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
