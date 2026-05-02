<x-app-layout>
    @php
        $palette = match($unit->book->level) {
            'Beginner' => ['accent' => 'bg-emerald-500', 'badge' => 'bg-emerald-500 text-white', 'text' => 'text-emerald-600 dark:text-emerald-400', 'soft' => 'bg-emerald-50 dark:bg-emerald-900/30', 'ring' => 'ring-emerald-200 dark:ring-emerald-800'],
            'Elementary' => ['accent' => 'bg-teal-500', 'badge' => 'bg-teal-500 text-white', 'text' => 'text-teal-600 dark:text-teal-400', 'soft' => 'bg-teal-50 dark:bg-teal-900/30', 'ring' => 'ring-teal-200 dark:ring-teal-800'],
            'Pre-Intermediate' => ['accent' => 'bg-cyan-500', 'badge' => 'bg-cyan-500 text-white', 'text' => 'text-cyan-600 dark:text-cyan-400', 'soft' => 'bg-cyan-50 dark:bg-cyan-900/30', 'ring' => 'ring-cyan-200 dark:ring-cyan-800'],
            'Intermediate' => ['accent' => 'bg-blue-500', 'badge' => 'bg-blue-500 text-white', 'text' => 'text-blue-600 dark:text-blue-400', 'soft' => 'bg-blue-50 dark:bg-blue-900/30', 'ring' => 'ring-blue-200 dark:ring-blue-800'],
            'Upper-Intermediate' => ['accent' => 'bg-indigo-500', 'badge' => 'bg-indigo-500 text-white', 'text' => 'text-indigo-600 dark:text-indigo-400', 'soft' => 'bg-indigo-50 dark:bg-indigo-900/30', 'ring' => 'ring-indigo-200 dark:ring-indigo-800'],
            'Advanced' => ['accent' => 'bg-purple-500', 'badge' => 'bg-purple-500 text-white', 'text' => 'text-purple-600 dark:text-purple-400', 'soft' => 'bg-purple-50 dark:bg-purple-900/30', 'ring' => 'ring-purple-200 dark:ring-purple-800'],
            default => ['accent' => 'bg-gray-500', 'badge' => 'bg-gray-500 text-white', 'text' => 'text-gray-600 dark:text-gray-400', 'soft' => 'bg-gray-50 dark:bg-gray-700/50', 'ring' => 'ring-gray-200 dark:ring-gray-700'],
        };

        $exDone = count($completed);
        $exPercent = (int) round(($exDone / 10) * 100);
        $wordsCount = $unit->words->count();
    @endphp

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('books.show', $unit->book) }}"
               class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition"
               aria-label="Volver">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
                    Unidad {{ $unit->unit_number }}: {{ $unit->title }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $unit->book->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('exercise_completed'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     class="flex items-center gap-3 p-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-medium">{{ session('exercise_completed') }}</span>
                    <button @click="show = false" class="ml-auto shrink-0 text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            @endif

            {{-- Summary card --}}
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>
                <div class="p-5 sm:p-6 pt-6 sm:pt-7">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $palette['badge'] }} shadow-sm">
                            {{ $unit->book->level }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Unidad {{ $unit->unit_number }} de {{ $unit->book->total_units }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="text-center p-3 rounded-md {{ $palette['soft'] }} ring-1 {{ $palette['ring'] }}">
                            <div class="text-2xl font-bold {{ $palette['text'] }}">{{ $wordsCount }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Palabras</div>
                        </div>
                        <div class="text-center p-3 rounded-md bg-amber-50 dark:bg-amber-900/30 ring-1 ring-amber-200 dark:ring-amber-800">
                            <div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $exDone }}/10</div>
                            <div class="text-[10px] uppercase tracking-wide text-amber-700/70 dark:text-amber-400/70">Ejercicios</div>
                        </div>
                        <div class="text-center p-3 rounded-md bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $progress->repetition_count }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Repeticiones</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Exercises section --}}
            <section class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Ejercicios</h3>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ $exDone }}/10 completados ({{ $exPercent }}%)
                    </span>
                </div>

                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden mb-5">
                    <div class="bg-emerald-500 h-2 rounded-full transition-all"
                         style="width: {{ max($exPercent, 2) }}%"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @php
                        $exerciseRoutes = [
                            1 => ['name' => 'units.exercises.listen-and-read', 'enabled' => true],
                            2 => ['name' => 'units.exercises.multichoice-english', 'enabled' => true],
                            3 => ['name' => 'units.exercises.multichoice-translation', 'enabled' => true],
                            4 => ['name' => 'units.exercises.match-definition', 'enabled' => true],
                            5 => ['name' => 'units.exercises.hangman', 'enabled' => true],
                            6 => ['name' => 'units.exercises.complete-story', 'enabled' => true],
                            7 => ['name' => 'units.exercises.write', 'enabled' => true],
                            8 => ['name' => 'units.exercises.read', 'enabled' => true],
                            9 => ['name' => 'units.exercises.organize-definition', 'enabled' => true],
                            10 => ['name' => 'units.exercises.organize-example', 'enabled' => true],
                        ];
                    @endphp
                    @foreach ($exerciseTypes as $type)
                        @php
                            $done = in_array($type->number, $completed, true);
                            $route = $exerciseRoutes[$type->number] ?? null;
                            $enabled = $route['enabled'] ?? false;
                            $optional = in_array($type->number, $optionalExercises, true);
                            $href = $enabled ? route($route['name'], $unit) : null;
                            $tag = $enabled ? 'a' : 'div';
                        @endphp
                        <{{ $tag }}
                            @if($enabled) href="{{ $href }}" wire:navigate @endif
                            class="flex items-center justify-between gap-3 p-3 rounded-md border transition
                                   {{ $done
                                        ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-900/20'
                                        : ($enabled
                                            ? ($optional ? 'border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-900/10 hover:border-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 cursor-pointer' : 'border-gray-200 dark:border-gray-700 hover:border-indigo-400 dark:hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer')
                                            : 'border-gray-200 dark:border-gray-700 opacity-60') }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="shrink-0 w-8 h-8 rounded-full {{ $done ? 'bg-emerald-500 text-white' : ($enabled ? ($optional ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' : 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300') : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300') }} flex items-center justify-center text-sm font-bold shadow-sm">
                                    @if ($done)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    @else
                                        {{ $type->number }}
                                    @endif
                                </span>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-medium text-sm text-gray-800 dark:text-gray-200 truncate">{{ $type->name }}</span>
                                    @if ($optional && ! $done)
                                        <span class="text-[10px] uppercase tracking-wide font-semibold text-amber-700 dark:text-amber-400">Opcional</span>
                                    @endif
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-medium {{ $done ? 'text-emerald-700 dark:text-emerald-300' : ($enabled ? ($optional ? 'text-amber-700 dark:text-amber-300' : 'text-indigo-700 dark:text-indigo-300') : 'text-gray-500 dark:text-gray-400') }}">
                                @if ($done)
                                    Completado
                                @elseif ($enabled)
                                    Empezar →
                                @else
                                    Próximamente
                                @endif
                            </span>
                        </{{ $tag }}>
                    @endforeach
                    @if (count($optionalExercises) > 0)
                        <p class="sm:col-span-2 text-xs text-amber-700 dark:text-amber-400 italic px-1 mt-1">
                            Desde tu repetición #{{ \App\Services\ProgressService::OPTIONAL_FROM_REPS }}, los ejercicios Organize son opcionales — completa los 8 primeros para registrar la repetición.
                        </p>
                    @endif
                </div>
            </section>

            {{-- Words section --}}
            <section class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5 sm:p-6">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Palabras de la unidad
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">({{ $wordsCount }})</span>
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
                    @foreach ($unit->words as $word)
                        <article class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        <span class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $word->text }}</span>
                                        @if ($word->type)
                                            <span class="text-xs font-medium px-1.5 py-0.5 rounded {{ $palette['soft'] }} {{ $palette['text'] }}">
                                                {{ $word->type }}
                                            </span>
                                        @endif
                                        @if ($word->phonetic)
                                            <span class="text-xs italic text-gray-500 dark:text-gray-400">/{{ $word->phonetic }}/</span>
                                        @endif
                                    </div>
                                    @if ($word->translation)
                                        <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ $word->translation }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($word->definition || $word->example)
                                <div class="space-y-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    @if ($word->definition)
                                        <div class="flex items-start gap-2">
                                            @if ($word->definition_audio)
                                                <button type="button"
                                                        x-data="{ playing: false }"
                                                        @click="if (playing) { $refs.audio.pause(); $refs.audio.currentTime = 0; playing = false; } else { $refs.audio.play(); playing = true; }"
                                                        class="shrink-0 w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 flex items-center justify-center hover:bg-indigo-200 dark:hover:bg-indigo-900/60 transition"
                                                        aria-label="Reproducir definición">
                                                    <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                                    </svg>
                                                    <svg x-show="playing" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                                    </svg>
                                                    <audio x-ref="audio" @ended="playing = false" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->definition_audio }}"></audio>
                                                </button>
                                            @endif
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <span class="text-[10px] uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-500">Definition: </span>
                                                {{ $word->definition }}
                                            </p>
                                        </div>
                                    @endif

                                    @if ($word->example)
                                        <div class="flex items-start gap-2">
                                            @if ($word->example_audio)
                                                <button type="button"
                                                        x-data="{ playing: false }"
                                                        @click="if (playing) { $refs.audio.pause(); $refs.audio.currentTime = 0; playing = false; } else { $refs.audio.play(); playing = true; }"
                                                        class="shrink-0 w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 flex items-center justify-center hover:bg-indigo-200 dark:hover:bg-indigo-900/60 transition"
                                                        aria-label="Reproducir ejemplo">
                                                    <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                                    </svg>
                                                    <svg x-show="playing" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                                    </svg>
                                                    <audio x-ref="audio" @ended="playing = false" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->example_audio }}"></audio>
                                                </button>
                                            @endif
                                            <p class="text-sm italic text-gray-600 dark:text-gray-400">
                                                <span class="text-[10px] uppercase tracking-wide font-semibold text-gray-500 dark:text-gray-500 not-italic">Example: </span>
                                                "{{ $word->example }}"
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if ($word->audio_file)
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <button type="button"
                                            x-data="{ playing: false }"
                                            @click="if (playing) { $refs.audio.pause(); $refs.audio.currentTime = 0; playing = false; } else { $refs.audio.play(); playing = true; }"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full {{ $palette['accent'] }} text-white text-sm font-medium hover:opacity-90 active:scale-95 transition"
                                            aria-label="Reproducir {{ $word->text }}">
                                        <svg x-show="!playing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                        </svg>
                                        <svg x-show="playing" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" style="display:none;">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1H8a1 1 0 01-1-1V9z" clip-rule="evenodd" />
                                        </svg>
                                        <span x-text="playing ? 'Detener' : 'Escuchar'">Escuchar</span>
                                        <audio x-ref="audio" @ended="playing = false" preload="none" src="{{ config('app.audio_base_url') }}/{{ $word->audio_file }}"></audio>
                                    </button>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
