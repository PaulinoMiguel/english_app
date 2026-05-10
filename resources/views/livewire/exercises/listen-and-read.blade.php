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
    public Unit $unit;

    public int $currentIndex = 0;

    public bool $finished = false;

    public bool $unlocked = false;

    /** @var array<int> Word IDs still active for this user (not currently known) */
    public array $activeWordIds = [];

    /** @var array<int> Filtered word IDs shuffled at mount; persists across requests */
    public array $shuffledIds = [];

    public function mount(Unit $unit, WordMasteryService $masterySvc): void
    {
        $this->unit = $unit->load('book', 'words');
        $this->activeWordIds = $masterySvc->activeWordsForUnit(auth()->id(), $this->unit)->pluck('id')->all();

        $this->shuffledIds = $this->unit->words
            ->filter(fn ($w) => in_array($w->id, $this->activeWordIds, true))
            ->pluck('id')
            ->shuffle()
            ->all();

        if (empty($this->shuffledIds)) {
            $this->finished = true;
        }
    }

    public function unlock(): void
    {
        $this->unlocked = true;
    }

    #[Computed]
    public function requiredKeys(): array
    {
        $w = $this->currentWord;
        if (! $w) return [];
        $keys = [];
        if ($w->audio_file) $keys[] = 'word';
        if ($w->definition_audio) $keys[] = 'def';
        if ($w->example_audio) $keys[] = 'ex';
        return $keys;
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
    public function total(): int
    {
        return $this->words->count();
    }

    #[Computed]
    public function isLast(): bool
    {
        return $this->currentIndex >= $this->total - 1;
    }

    public function previous(): void
    {
        if ($this->currentIndex > 0) {
            $this->currentIndex--;
            $this->unlocked = false;
        }
    }

    public function next(): void
    {
        if (! $this->unlocked) return;

        if ($this->currentIndex < $this->total - 1) {
            $this->currentIndex++;
            $this->unlocked = false;
        } else {
            $this->finished = true;
        }
    }

    public function complete(ProgressService $service): void
    {
        $service->markExerciseCompleted(auth()->id(), $this->unit->id, 1);

        session()->flash('exercise_completed', '¡Ejercicio "Listen and Read" completado!');
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
                    Listen and Read · Unidad {{ $unit->unit_number }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            @if ($this->total === 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400">Esta unidad no tiene palabras todavía.</p>
                    <a href="{{ route('units.show', $unit) }}" wire:navigate class="inline-block mt-4 text-sm font-medium {{ $palette['text'] }} hover:underline">
                        Volver a la unidad
                    </a>
                </div>
            @elseif ($finished)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500 text-white mb-4 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">¡Terminaste de escuchar!</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Escuchaste y leíste las {{ $this->total }} palabras de la unidad.</p>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <button wire:click="complete"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg bg-emerald-500 text-white font-semibold hover:bg-emerald-600 active:scale-95 transition disabled:opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Marcar ejercicio como completado
                        </button>
                        <a href="{{ route('units.show', $unit) }}" wire:navigate
                           class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Volver sin guardar
                        </a>
                    </div>
                </div>
            @else
                {{-- Progress header --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            Palabra {{ $currentIndex + 1 }} de {{ $this->total }}
                        </span>
                        <span class="font-bold {{ $palette['text'] }}">
                            {{ (int) round((($currentIndex + 1) / $this->total) * 100) }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                        <div class="{{ $palette['accent'] }} h-2 rounded-full transition-all"
                             style="width: {{ (($currentIndex + 1) / $this->total) * 100 }}%"></div>
                    </div>
                </div>

                {{-- Listen card --}}
                @php($word = $this->currentWord)
                <div wire:key="listen-{{ $word->id }}"
                     x-data="{
                        stage: null,
                        playedWord: false,
                        playedDef: false,
                        playedEx: false,
                        notified: false,
                        needWord: {{ $word->audio_file ? 'true' : 'false' }},
                        needDef: {{ $word->definition_audio ? 'true' : 'false' }},
                        needEx: {{ $word->example_audio ? 'true' : 'false' }},
                        markDone(key) {
                            if (key === 'word') this.playedWord = true;
                            else if (key === 'def') this.playedDef = true;
                            else if (key === 'ex') this.playedEx = true;
                            this.checkAllDone();
                        },
                        checkAllDone() {
                            if (this.notified) return;
                            let done = (!this.needWord || this.playedWord)
                                    && (!this.needDef || this.playedDef)
                                    && (!this.needEx || this.playedEx);
                            if (done) {
                                this.notified = true;
                                $wire.unlock();
                            }
                        },
                        stopAll() {
                            ['word','def','ex'].forEach(k => {
                                let a = this.$refs[k];
                                if (a) { a.pause(); a.currentTime = 0; a.onended = null; }
                            });
                            this.stage = null;
                        },
                        playOne(key) {
                            this.stopAll();
                            let a = this.$refs[key];
                            if (!a) return;
                            this.stage = key;
                            a.onended = () => { this.markDone(key); this.stage = null; };
                            a.play().catch(() => { this.markDone(key); this.stage = null; });
                        },
                        playAll() {
                            if (this.stage) { this.stopAll(); return; }
                            let seq = [];
                            if (this.needWord) seq.push('word');
                            if (this.needDef) seq.push('def');
                            if (this.needEx) seq.push('ex');
                            this.playSequence(seq);
                        },
                        playSequence(keys) {
                            if (keys.length === 0) { this.stage = null; return; }
                            let key = keys[0];
                            let rest = keys.slice(1);
                            let a = this.$refs[key];
                            if (!a) { this.markDone(key); this.playSequence(rest); return; }
                            this.stage = key;
                            a.currentTime = 0;
                            a.onended = () => { this.markDone(key); this.playSequence(rest); };
                            a.play().catch(() => { this.markDone(key); this.playSequence(rest); });
                        },
                     }">

                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>

                    {{-- Hidden audio elements. @ended marks played; markDone notifies server when all 3 done. --}}
                    @if ($word->audio_file)
                        <audio x-ref="word" x-on:ended="markDone('word')" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->audio_file }}"></audio>
                    @endif
                    @if ($word->definition_audio)
                        <audio x-ref="def" x-on:ended="markDone('def')" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->definition_audio }}"></audio>
                    @endif
                    @if ($word->example_audio)
                        <audio x-ref="ex" x-on:ended="markDone('ex')" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->example_audio }}"></audio>
                    @endif

                    <div class="p-6 sm:p-8">
                        {{-- Word header --}}
                        <div class="text-center mb-5 pb-5 border-b border-gray-100 dark:border-gray-700">
                            <div class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                {{ $word->text }}
                            </div>
                            <div class="flex items-center justify-center gap-2 flex-wrap mb-3">
                                @if ($word->phonetic)
                                    <span class="text-sm italic text-gray-500 dark:text-gray-400">/{{ $word->phonetic }}/</span>
                                @endif
                                @if ($word->type)
                                    <span class="text-xs font-medium px-2 py-0.5 rounded {{ $palette['soft'] }} {{ $palette['text'] }}">
                                        {{ $word->type }}
                                    </span>
                                @endif
                            </div>
                            @if ($word->translation)
                                <p class="text-base text-gray-700 dark:text-gray-300">{{ $word->translation }}</p>
                            @endif
                        </div>

                        {{-- Definition with audio --}}
                        @if ($word->definition)
                            <div class="mb-4 p-4 rounded-lg {{ $palette['soft'] }}" :class="stage === 'def' ? 'ring-2 {{ str_replace('text-', 'ring-', $palette['text']) }}' : ''">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-xs uppercase tracking-wide font-bold {{ $palette['text'] }}">Definition</span>
                                    @if ($word->definition_audio)
                                        <button type="button"
                                                @click="playOne('def')"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $palette['accent'] }} text-white hover:opacity-90 active:scale-95 transition shadow-sm"
                                                aria-label="Reproducir definición">
                                            <svg x-show="stage !== 'def'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                            </svg>
                                            <svg x-show="stage === 'def'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <p class="text-sm sm:text-base text-gray-800 dark:text-gray-200">{{ $word->definition }}</p>
                            </div>
                        @endif

                        {{-- Example with audio --}}
                        @if ($word->example)
                            <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 ring-1 ring-gray-200 dark:ring-gray-700" :class="stage === 'ex' ? 'ring-2 ring-gray-500' : ''">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-xs uppercase tracking-wide font-bold text-gray-700 dark:text-gray-300">Example</span>
                                    @if ($word->example_audio)
                                        <button type="button"
                                                @click="playOne('ex')"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-700 dark:bg-gray-600 text-white hover:opacity-90 active:scale-95 transition shadow-sm"
                                                aria-label="Reproducir ejemplo">
                                            <svg x-show="stage !== 'ex'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                            </svg>
                                            <svg x-show="stage === 'ex'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <p class="text-sm sm:text-base italic text-gray-800 dark:text-gray-200">"{{ $word->example }}"</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Bottom action area: play button just above nav so the thumb can reach both --}}
                <div class="mt-5">
                    @if ($word->audio_file)
                        <div class="text-center mb-4">
                            <button type="button"
                                    @click="playAll()"
                                    class="inline-flex items-center gap-2 px-6 py-3 rounded-full {{ $palette['accent'] }} text-white font-semibold hover:opacity-90 active:scale-95 transition shadow-md">
                                <svg x-show="stage === null" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                </svg>
                                <svg x-show="stage !== null" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                </svg>
                                <span x-text="stage === null ? 'Escuchar palabra' : (stage === 'word' ? 'Reproduciendo palabra…' : (stage === 'def' ? 'Reproduciendo definición…' : 'Reproduciendo ejemplo…'))">Escuchar palabra</span>
                            </button>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                                Palabra
                                <span x-show="playedWord" class="text-emerald-600 dark:text-emerald-400 font-semibold">✓</span>
                                · Definición
                                <span x-show="playedDef" class="text-emerald-600 dark:text-emerald-400 font-semibold">✓</span>
                                · Ejemplo
                                <span x-show="playedEx" class="text-emerald-600 dark:text-emerald-400 font-semibold">✓</span>
                            </p>
                        </div>
                    @endif

                    @if (! $unlocked)
                        <p class="text-xs text-center text-amber-700 dark:text-amber-400 mb-3 font-medium">
                            Escucha los 3 audios para avanzar.
                        </p>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <button wire:click="previous"
                                @disabled($currentIndex === 0)
                                class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Anterior
                        </button>

                        <button wire:click="next"
                                @disabled(! $unlocked)
                                class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg {{ $unlocked ? $palette['accent'].' text-white hover:opacity-90 active:scale-95 shadow-md' : 'bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed' }} font-semibold transition">
                            @if ($this->isLast)
                                Terminar
                            @else
                                Siguiente
                            @endif
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
                </div>
            @endif
        </div>
    </div>
</div>
