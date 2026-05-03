<?php

use App\Models\StoryWord;
use App\Models\Unit;
use App\Services\ProgressService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Unit $unit;

    /** @var array<int, string> Tokens of the full story in order (text per token) */
    public array $storyTokens = [];

    /** @var array<int, bool> Whether each story token is a core blank */
    public array $isCoreFlags = [];

    /** @var array<int, string> Expected text for each slot (in order of appearance) */
    public array $slotExpected = [];

    /** @var array<int, string> Bank items in shuffled order (text only) */
    public array $bankTexts = [];

    /** @var array<int, ?int> placements[slotIndex] = bankIndex (or null) */
    public array $placements = [];

    public ?int $selectedBankIndex = null;

    public bool $answered = false;

    public bool $allCorrect = false;

    public int $attempts = 0;

    public int $wrongAttempts = 0;

    public bool $finished = false;

    public function mount(Unit $unit): void
    {
        $this->unit = $unit->load('book');

        $story = StoryWord::where('unit_id', $unit->id)->orderBy('order')->get();

        if ($story->isEmpty()) {
            $this->finished = true;
            return;
        }

        $tokens = [];
        $flags = [];
        $coreTexts = [];
        foreach ($story as $sw) {
            $tokens[] = $sw->text;
            $flags[] = (bool) $sw->is_core;
            if ($sw->is_core) {
                $coreTexts[] = $sw->text;
            }
        }

        if (count($coreTexts) === 0) {
            $this->finished = true;
            return;
        }

        $this->storyTokens = $tokens;
        $this->isCoreFlags = $flags;
        $this->slotExpected = $coreTexts;

        $bank = $coreTexts;
        sort($bank, SORT_FLAG_CASE | SORT_STRING); // alphabetical, case-insensitive
        $this->bankTexts = $bank;

        $this->placements = array_fill(0, count($coreTexts), null);
    }

    #[Computed]
    public function totalSlots(): int
    {
        return count($this->slotExpected);
    }

    #[Computed]
    public function filledCount(): int
    {
        return count(array_filter($this->placements, fn ($p) => $p !== null));
    }

    #[Computed]
    public function isComplete(): bool
    {
        return $this->filledCount === $this->totalSlots;
    }

    #[Computed]
    public function usedBankIndices(): array
    {
        return array_filter($this->placements, fn ($p) => $p !== null);
    }

    public function selectBankItem(int $bankIndex): void
    {
        if ($this->answered) return;
        if (! isset($this->bankTexts[$bankIndex])) return;
        if (in_array($bankIndex, $this->placements, true)) return;

        // Toggle: clicking the already-selected item deselects it
        $this->selectedBankIndex = $this->selectedBankIndex === $bankIndex ? null : $bankIndex;
    }

    public function placeInSlot(int $slotIndex): void
    {
        if ($this->answered) return;
        if (! array_key_exists($slotIndex, $this->placements)) return;
        if ($this->selectedBankIndex === null) return;

        // If slot already has a word, return it to the bank first
        if ($this->placements[$slotIndex] !== null) {
            // The previously placed word is freed; we place the selected one here
        }

        $this->placements[$slotIndex] = $this->selectedBankIndex;
        $this->selectedBankIndex = null;
    }

    public function removeFromSlot(int $slotIndex): void
    {
        if ($this->answered) return;
        if (! array_key_exists($slotIndex, $this->placements)) return;
        $this->placements[$slotIndex] = null;
        $this->selectedBankIndex = null;
    }

    public function clearAll(): void
    {
        if ($this->answered) return;
        $this->placements = array_fill(0, count($this->slotExpected), null);
        $this->selectedBankIndex = null;
    }

    public function submit(): void
    {
        if ($this->answered || ! $this->isComplete) return;

        $this->attempts++;

        $allCorrect = true;
        foreach ($this->placements as $slotIndex => $bankIndex) {
            $given = $this->bankTexts[$bankIndex] ?? '';
            $expected = $this->slotExpected[$slotIndex] ?? '';
            if ($given !== $expected) {
                $allCorrect = false;
            }
        }

        $this->allCorrect = $allCorrect;
        $this->answered = true;

        if (! $allCorrect) {
            $this->wrongAttempts++;
        }
    }

    public function retry(): void
    {
        // Keep correct slots, clear only the wrong ones; mark as still "playing"
        foreach ($this->placements as $slotIndex => $bankIndex) {
            if ($bankIndex === null) continue;
            $given = $this->bankTexts[$bankIndex] ?? '';
            $expected = $this->slotExpected[$slotIndex] ?? '';
            if ($given !== $expected) {
                $this->placements[$slotIndex] = null;
            }
        }

        $this->answered = false;
        $this->allCorrect = false;
        $this->selectedBankIndex = null;
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 6);

        session()->flash('exercise_completed', '¡Ejercicio "Complete Story" completado!');
        $this->redirect(route('units.show', $this->unit), navigate: true);
    }

    public function slotState(int $slotIndex): string
    {
        if (! $this->answered) {
            return $this->placements[$slotIndex] === null ? 'empty' : 'filled';
        }
        $bankIndex = $this->placements[$slotIndex];
        if ($bankIndex === null) return 'empty';
        $given = $this->bankTexts[$bankIndex] ?? '';
        $expected = $this->slotExpected[$slotIndex] ?? '';
        return $given === $expected ? 'correct' : 'wrong';
    }

    public function slotClass(string $state): string
    {
        if ($state === 'empty') {
            return 'border-gray-400 dark:border-gray-500 text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-700/40';
        }
        if ($state === 'filled') {
            return 'border-indigo-400 dark:border-indigo-500 text-gray-900 dark:text-gray-100 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-rose-50 dark:hover:bg-rose-900/20 hover:border-rose-400';
        }
        if ($state === 'correct') {
            return 'border-emerald-500 text-emerald-800 dark:text-emerald-200 bg-emerald-50 dark:bg-emerald-900/30 cursor-default';
        }
        return 'border-rose-500 text-rose-800 dark:text-rose-200 bg-rose-50 dark:bg-rose-900/30 cursor-default';
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
                    Complete Story · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            @if ($this->totalSlots === 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400">Esta unidad no tiene un cuento disponible.</p>
                    <a href="{{ route('units.show', $unit) }}" wire:navigate class="inline-block mt-4 text-sm font-medium {{ $palette['text'] }} hover:underline">
                        Volver a la unidad
                    </a>
                </div>
            @else
                {{-- Progress header --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            {{ $this->filledCount }} / {{ $this->totalSlots }} huecos
                        </span>
                        <div class="flex items-center gap-3">
                            <span class="text-gray-600 dark:text-gray-400">Intentos: {{ $attempts }}</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="{{ $palette['accent'] }} h-2 rounded-full transition-all"
                             style="width: {{ ($this->filledCount / $this->totalSlots) * 100 }}%"></div>
                    </div>
                </div>

                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-[calc(100dvh-180px)] sm:h-auto sm:block">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>

                    {{-- Top: hint + story (scrollable on mobile) --}}
                    <div class="flex-1 min-h-0 overflow-y-auto sm:overflow-visible p-5 sm:p-6 sm:pb-0">

                        <p class="text-xs uppercase tracking-wide font-semibold {{ $palette['text'] }} mb-3">
                            Lee el cuento y rellena los huecos
                        </p>

                        {{-- Story body --}}
                        <div class="text-base sm:text-lg leading-loose text-gray-800 dark:text-gray-200 sm:mb-6">
                            @php
                                $slotCounter = 0;
                            @endphp
                            @foreach ($storyTokens as $i => $token)
                                @if (! $isCoreFlags[$i])
                                    <span>{{ $token }}</span>
                                @else
                                    @php
                                        $slotIndex = $slotCounter++;
                                        $bankIndex = $placements[$slotIndex] ?? null;
                                        $state = $this->slotState($slotIndex);
                                        $cls = $this->slotClass($state);
                                        $showRemoveBtn = ! $answered && $state === 'filled';
                                        $showWrongHint = $answered && $state === 'wrong';
                                        $slotText = $state === 'empty' ? ('#'.($slotIndex + 1)) : ($bankTexts[$bankIndex] ?? '—');
                                        $borderStyle = $state === 'empty' ? 'border-dashed' : '';
                                        $fontStyle = $state === 'empty' ? 'text-xs font-mono' : 'font-semibold';
                                    @endphp
                                    @php
                                        $hasSelection = $selectedBankIndex !== null && ! $answered;
                                        $isPlaceable = $state === 'empty' && $hasSelection;
                                        $clickAction = null;
                                        if ($state === 'empty' && $hasSelection) {
                                            $clickAction = 'placeInSlot';
                                        } elseif ($state === 'filled' && ! $answered) {
                                            $clickAction = 'removeFromSlot';
                                        }
                                        $extraCls = $isPlaceable
                                            ? 'ring-2 ring-indigo-400 dark:ring-indigo-500 cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-900/40'
                                            : '';
                                    @endphp
                                    @if ($clickAction)
                                        <button wire:click="{{ $clickAction }}({{ $slotIndex }})"
                                                class="inline-block min-w-[60px] sm:min-w-[80px] px-2 py-0.5 rounded border-2 text-center align-middle transition {{ $borderStyle }} {{ $fontStyle }} {{ $cls }} {{ $extraCls }}">
                                            {{ $slotText }}
                                        </button>
                                    @else
                                        <span class="inline-block min-w-[60px] sm:min-w-[80px] px-2 py-0.5 rounded border-2 text-center align-middle {{ $borderStyle }} {{ $fontStyle }} {{ $cls }}">
                                            {{ $slotText }}
                                            @if ($showWrongHint)
                                                <span class="block text-[10px] text-rose-600 dark:text-rose-400">→ {{ $slotExpected[$slotIndex] }}</span>
                                            @endif
                                        </span>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                    {{-- /scrollable top --}}

                    {{-- Bottom: bank + actions (fixed-size on mobile, normal on desktop) --}}
                    <div class="shrink-0 sm:shrink p-5 pt-3 sm:p-6 sm:pt-0 border-t sm:border-t-0 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 max-h-[55vh] sm:max-h-none overflow-y-auto sm:overflow-visible">

                        {{-- Bank --}}
                        @if (! $answered || ! $allCorrect)
                            <div class="sm:border-t sm:border-gray-200 dark:sm:border-gray-700 sm:pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-400">
                                        Palabras disponibles ({{ count($bankTexts) - count($this->usedBankIndices) }})
                                    </p>
                                    @if (! $answered && $this->filledCount > 0)
                                        <button wire:click="clearAll" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline">
                                            Limpiar
                                        </button>
                                    @endif
                                </div>

                                @if ($selectedBankIndex !== null && ! $answered)
                                    <p class="text-xs text-indigo-600 dark:text-indigo-400 mb-2 font-medium">
                                        Toca un hueco para colocar <span class="font-bold">"{{ $bankTexts[$selectedBankIndex] ?? '' }}"</span>
                                    </p>
                                @endif

                                <div class="flex flex-wrap gap-2">
                                    @foreach ($bankTexts as $bIdx => $text)
                                        @php
                                            $used = in_array($bIdx, $placements, true);
                                            $isSelected = $selectedBankIndex === $bIdx;
                                            $bankCls = $used
                                                ? 'bg-gray-100 dark:bg-gray-700/50 text-gray-300 dark:text-gray-600 line-through cursor-not-allowed'
                                                : ($isSelected
                                                    ? 'bg-indigo-500 text-white ring-2 ring-indigo-400 cursor-pointer scale-105 shadow-md'
                                                    : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 ring-1 ring-gray-300 dark:ring-gray-600 hover:ring-indigo-400 dark:hover:ring-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer active:scale-95');
                                        @endphp
                                        <button wire:click="selectBankItem({{ $bIdx }})"
                                                @disabled($used || $answered)
                                                class="px-3 py-1.5 rounded-md font-medium text-sm transition {{ $bankCls }}">
                                            {{ $text }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Action buttons --}}
                        <div class="mt-5">
                            @if (! $answered)
                                <button wire:click="submit"
                                        @disabled(! $this->isComplete)
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md disabled:opacity-40 disabled:cursor-not-allowed">
                                    Comprobar
                                </button>
                            @elseif ($allCorrect)
                                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800 text-center mb-3">
                                    <p class="text-emerald-700 dark:text-emerald-300 font-semibold">¡Cuento completado correctamente! 🎉</p>
                                </div>
                                <button wire:click="complete"
                                        wire:loading.attr="disabled"
                                        x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-emerald-500 text-white font-semibold hover:bg-emerald-600 active:scale-95 transition shadow-md disabled:opacity-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    Marcar ejercicio como completado
                                </button>
                            @else
                                @php($wrongCount = collect(range(0, $this->totalSlots - 1))->filter(fn ($i) => $this->slotState($i) === 'wrong')->count())
                                <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 ring-1 ring-rose-200 dark:ring-rose-800 text-center mb-3">
                                    <p class="text-rose-700 dark:text-rose-300 font-semibold">
                                        Faltan {{ $wrongCount }} {{ $wrongCount === 1 ? 'palabra' : 'palabras' }} por corregir.
                                    </p>
                                    <p class="text-xs text-rose-600/80 dark:text-rose-400/80 mt-1">
                                        Las que están bien quedan marcadas en verde. Reintenta solo las rojas.
                                    </p>
                                </div>
                                <button wire:click="retry"
                                        x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                    </svg>
                                    Reintentar
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
