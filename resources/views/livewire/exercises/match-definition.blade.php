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

    /** @var array<int> All word IDs in the exercise */
    public array $allWordIds = [];

    /** @var array<int> Word IDs visible in left column right now */
    public array $availableWords = [];

    /** @var array<int> Word IDs whose definition is visible in right column right now */
    public array $availableDefs = [];

    /** @var array<int> Word IDs already mastered (matched correctly) */
    public array $masteredIds = [];

    public ?int $selectedId = null;

    public ?string $selectedColumn = null; // 'word' | 'def'

    public int $correct = 0;

    public int $wrong = 0;

    public int $currentRound = 1;

    public bool $finished = false;

    public function mount(Unit $unit): void
    {
        $this->unit = $unit->load('book', 'words');

        $words = $this->unit->words->filter(fn ($w) => ! empty($w->definition));

        if ($words->count() < 2) {
            $this->finished = true;
            return;
        }

        $this->allWordIds = $words->pluck('id')->all();
        $this->availableWords = $this->allWordIds;
        $defOrder = $this->allWordIds;
        shuffle($defOrder);
        $this->availableDefs = $defOrder;
    }

    #[Computed]
    public function leftWords(): Collection
    {
        $byId = $this->unit->words->keyBy('id');
        return collect($this->availableWords)->map(fn ($id) => $byId[$id] ?? null)->filter()->values();
    }

    #[Computed]
    public function rightDefs(): Collection
    {
        $byId = $this->unit->words->keyBy('id');
        return collect($this->availableDefs)->map(fn ($id) => $byId[$id] ?? null)->filter()->values();
    }

    #[Computed]
    public function totalPairs(): int
    {
        return count($this->allWordIds);
    }

    #[Computed]
    public function masteredCount(): int
    {
        return count($this->masteredIds);
    }

    public function maskedDefinition(Word $w): string
    {
        if (empty($w->definition)) return '';
        $mask = str_repeat('_', mb_strlen($w->text));
        return preg_replace('/\b'.preg_quote($w->text, '/').'\w*/iu', $mask, $w->definition);
    }

    public function selectFrom(string $column, int $wordId): void
    {
        if ($this->finished) return;
        if (! in_array($column, ['word', 'def'], true)) return;

        // Verify availability in its column
        $pool = $column === 'word' ? $this->availableWords : $this->availableDefs;
        if (! in_array($wordId, $pool, true)) return;

        // First selection or re-selecting in same column → just update selection
        if ($this->selectedId === null || $this->selectedColumn === $column) {
            $this->selectedId = $wordId;
            $this->selectedColumn = $column;
            return;
        }

        // Other column → process the pair
        $wordIdSelected = $this->selectedColumn === 'word' ? $this->selectedId : $wordId;
        $defWordId = $this->selectedColumn === 'def' ? $this->selectedId : $wordId;

        // Remove both from current round's availability (silently)
        $this->availableWords = array_values(array_diff($this->availableWords, [$wordIdSelected]));
        $this->availableDefs = array_values(array_diff($this->availableDefs, [$defWordId]));

        if ($wordIdSelected === $defWordId) {
            $this->masteredIds[] = $wordIdSelected;
            $this->correct++;
        } else {
            $this->wrong++;
        }

        $this->selectedId = null;
        $this->selectedColumn = null;

        // End of round?
        if (empty($this->availableWords) && empty($this->availableDefs)) {
            $this->advanceRound();
        }
    }

    private function advanceRound(): void
    {
        $remaining = array_values(array_diff($this->allWordIds, $this->masteredIds));

        if (empty($remaining)) {
            $this->finished = true;
            return;
        }

        $this->currentRound++;
        $this->availableWords = $remaining;
        $defOrder = $remaining;
        shuffle($defOrder);
        $this->availableDefs = $defOrder;
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 4);

        session()->flash('exercise_completed', '¡Ejercicio "Match Word and Definition" completado!');
        $this->redirect(route('units.show', $this->unit), navigate: true);
    }

    public function cellClass(string $column, int $wordId, string $selectClass, string $textClass): string
    {
        $isSelected = $this->selectedColumn === $column && $this->selectedId === $wordId;

        if ($isSelected) {
            return $selectClass.' '.$textClass.' font-bold';
        }
        return 'border-gray-300 dark:border-gray-600 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 cursor-pointer';
    }

    public function with(): array
    {
        $palette = match($this->unit->book->level) {
            'Beginner' => ['accent' => 'bg-emerald-500', 'badge' => 'bg-emerald-500 text-white', 'text' => 'text-emerald-600 dark:text-emerald-400', 'soft' => 'bg-emerald-50 dark:bg-emerald-900/30', 'select' => 'border-emerald-500 ring-2 ring-emerald-500/40 bg-emerald-50 dark:bg-emerald-900/30'],
            'Elementary' => ['accent' => 'bg-teal-500', 'badge' => 'bg-teal-500 text-white', 'text' => 'text-teal-600 dark:text-teal-400', 'soft' => 'bg-teal-50 dark:bg-teal-900/30', 'select' => 'border-teal-500 ring-2 ring-teal-500/40 bg-teal-50 dark:bg-teal-900/30'],
            'Pre-Intermediate' => ['accent' => 'bg-cyan-500', 'badge' => 'bg-cyan-500 text-white', 'text' => 'text-cyan-600 dark:text-cyan-400', 'soft' => 'bg-cyan-50 dark:bg-cyan-900/30', 'select' => 'border-cyan-500 ring-2 ring-cyan-500/40 bg-cyan-50 dark:bg-cyan-900/30'],
            'Intermediate' => ['accent' => 'bg-blue-500', 'badge' => 'bg-blue-500 text-white', 'text' => 'text-blue-600 dark:text-blue-400', 'soft' => 'bg-blue-50 dark:bg-blue-900/30', 'select' => 'border-blue-500 ring-2 ring-blue-500/40 bg-blue-50 dark:bg-blue-900/30'],
            'Upper-Intermediate' => ['accent' => 'bg-indigo-500', 'badge' => 'bg-indigo-500 text-white', 'text' => 'text-indigo-600 dark:text-indigo-400', 'soft' => 'bg-indigo-50 dark:bg-indigo-900/30', 'select' => 'border-indigo-500 ring-2 ring-indigo-500/40 bg-indigo-50 dark:bg-indigo-900/30'],
            'Advanced' => ['accent' => 'bg-purple-500', 'badge' => 'bg-purple-500 text-white', 'text' => 'text-purple-600 dark:text-purple-400', 'soft' => 'bg-purple-50 dark:bg-purple-900/30', 'select' => 'border-purple-500 ring-2 ring-purple-500/40 bg-purple-50 dark:bg-purple-900/30'],
            default => ['accent' => 'bg-gray-500', 'badge' => 'bg-gray-500 text-white', 'text' => 'text-gray-600 dark:text-gray-400', 'soft' => 'bg-gray-50 dark:bg-gray-700/50', 'select' => 'border-gray-500 ring-2 ring-gray-500/40 bg-gray-50 dark:bg-gray-700/30'],
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
                    Match Word & Definition · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($finished)
                @php($total = $correct + $wrong)
                @php($score = $total > 0 ? (int) round(($correct / $total) * 100) : 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500 text-white mb-4 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">¡Ejercicio terminado!</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Emparejaste correctamente las {{ $this->totalPairs }} palabras en {{ $currentRound }} {{ $currentRound === 1 ? 'ronda' : 'rondas' }}.</p>
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
                            <div class="text-2xl font-bold {{ $palette['text'] }}">{{ $score }}%</div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Precisión</div>
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
                {{-- Progress + score header --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            Ronda {{ $currentRound }} ·
                            <span class="text-emerald-600 dark:text-emerald-400">{{ $this->masteredCount }} / {{ $this->totalPairs }} dominadas</span>
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ {{ $correct }}</span>
                            <span class="text-rose-600 dark:text-rose-400 font-semibold">✗ {{ $wrong }}</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="{{ $palette['accent'] }} h-2 rounded-full transition-all"
                             style="width: {{ $this->totalPairs > 0 ? ($this->masteredCount / $this->totalPairs) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>
                    <div class="p-5 sm:p-6">
                        <p class="text-center text-sm text-gray-600 dark:text-gray-400 mb-1">
                            Toca una palabra y luego su definición correspondiente.
                        </p>
                        @if ($currentRound > 1)
                            <p class="text-center text-xs text-amber-700 dark:text-amber-400 mb-5 font-medium">
                                Ronda {{ $currentRound }} — vuelven las que faltaron por dominar.
                            </p>
                        @else
                            <div class="mb-5"></div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {{-- Definitions column (top on mobile, left on desktop) --}}
                            <div>
                                <h4 class="text-xs uppercase tracking-wide font-bold text-gray-500 dark:text-gray-400 mb-2 px-1 sticky top-0 bg-white dark:bg-gray-800 py-1">
                                    Definitions ({{ count($availableDefs) }})
                                </h4>
                                <div class="space-y-2">
                                    @foreach ($this->rightDefs as $d)
                                        <button wire:click="selectFrom('def', {{ $d->id }})"
                                                class="w-full px-4 py-3 rounded-lg border-2 text-sm text-left transition-all {{ $this->cellClass('def', $d->id, $palette['select'], $palette['text']) }}">
                                            {{ $this->maskedDefinition($d) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Words column (bottom on mobile, right on desktop) --}}
                            <div>
                                <h4 class="text-xs uppercase tracking-wide font-bold text-gray-500 dark:text-gray-400 mb-2 px-1 sticky top-0 bg-white dark:bg-gray-800 py-1">
                                    Palabras ({{ count($availableWords) }})
                                </h4>
                                <div class="space-y-2">
                                    @foreach ($this->leftWords as $w)
                                        <button wire:click="selectFrom('word', {{ $w->id }})"
                                                class="w-full px-4 py-3 rounded-lg border-2 font-semibold text-base text-left transition-all {{ $this->cellClass('word', $w->id, $palette['select'], $palette['text']) }}">
                                            {{ $w->text }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
