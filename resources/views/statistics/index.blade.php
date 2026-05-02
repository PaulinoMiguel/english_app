<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Estadísticas
        </h2>
    </x-slot>

    @php
        $totalBooks = $books->count();
        $booksStarted = 0;
        $booksCompleted = 0;
        $totalRepetitions = 0;
        $totalUnitsMastered = 0;
        foreach ($stats as $s) {
            if ($s->hasStarted()) $booksStarted++;
            if ($s->isCompleted()) $booksCompleted++;
            $totalRepetitions += $s->totalRepetitions;
            $totalUnitsMastered += $s->unitsMastered;
        }

        $palettes = [
            'Beginner' => ['accent' => 'bg-emerald-500', 'badge' => 'bg-emerald-500 text-white', 'text' => 'text-emerald-600 dark:text-emerald-400', 'soft' => 'bg-emerald-50 dark:bg-emerald-900/30', 'ring' => 'ring-emerald-200 dark:ring-emerald-800'],
            'Elementary' => ['accent' => 'bg-teal-500', 'badge' => 'bg-teal-500 text-white', 'text' => 'text-teal-600 dark:text-teal-400', 'soft' => 'bg-teal-50 dark:bg-teal-900/30', 'ring' => 'ring-teal-200 dark:ring-teal-800'],
            'Pre-Intermediate' => ['accent' => 'bg-cyan-500', 'badge' => 'bg-cyan-500 text-white', 'text' => 'text-cyan-600 dark:text-cyan-400', 'soft' => 'bg-cyan-50 dark:bg-cyan-900/30', 'ring' => 'ring-cyan-200 dark:ring-cyan-800'],
            'Intermediate' => ['accent' => 'bg-blue-500', 'badge' => 'bg-blue-500 text-white', 'text' => 'text-blue-600 dark:text-blue-400', 'soft' => 'bg-blue-50 dark:bg-blue-900/30', 'ring' => 'ring-blue-200 dark:ring-blue-800'],
            'Upper-Intermediate' => ['accent' => 'bg-indigo-500', 'badge' => 'bg-indigo-500 text-white', 'text' => 'text-indigo-600 dark:text-indigo-400', 'soft' => 'bg-indigo-50 dark:bg-indigo-900/30', 'ring' => 'ring-indigo-200 dark:ring-indigo-800'],
            'Advanced' => ['accent' => 'bg-purple-500', 'badge' => 'bg-purple-500 text-white', 'text' => 'text-purple-600 dark:text-purple-400', 'soft' => 'bg-purple-50 dark:bg-purple-900/30', 'ring' => 'ring-purple-200 dark:ring-purple-800'],
        ];
    @endphp

    <div class="py-6 sm:py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            {{-- Overall progress hero --}}
            <div class="rounded-2xl p-6 sm:p-8 shadow-lg bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white">
                <div class="flex items-center gap-3 mb-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zM4 4h3a3 3 0 006 0h3a2 2 0 012 2v9a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm2.5 7a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm2.45 4a2.5 2.5 0 10-4.9 0h4.9zM12 9a1 1 0 100 2h3a1 1 0 100-2h-3zm-1 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                    <h3 class="text-xl sm:text-2xl font-bold">Progreso general</h3>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white/20 rounded-xl p-4 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-wide text-white/80 font-medium mb-1">Libros</p>
                        <p class="text-3xl font-bold">{{ $booksStarted }}<span class="text-lg text-white/80">/{{ $totalBooks }}</span></p>
                        <p class="text-xs text-white/70 mt-0.5">iniciados</p>
                    </div>
                    <div class="bg-white/20 rounded-xl p-4 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-wide text-white/80 font-medium mb-1">Completados</p>
                        <p class="text-3xl font-bold">{{ $booksCompleted }}</p>
                        <p class="text-xs text-white/70 mt-0.5">{{ $booksCompleted === 1 ? 'libro' : 'libros' }}</p>
                    </div>
                    <div class="bg-white/20 rounded-xl p-4 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-wide text-white/80 font-medium mb-1">Repeticiones</p>
                        <p class="text-3xl font-bold">{{ $totalRepetitions }}</p>
                        <p class="text-xs text-white/70 mt-0.5">totales</p>
                    </div>
                    <div class="bg-white/20 rounded-xl p-4 backdrop-blur-sm">
                        <p class="text-xs uppercase tracking-wide text-white/80 font-medium mb-1">Dominadas</p>
                        <p class="text-3xl font-bold">{{ $totalUnitsMastered }}</p>
                        <p class="text-xs text-white/70 mt-0.5">{{ $totalUnitsMastered === 1 ? 'unidad' : 'unidades' }}</p>
                    </div>
                </div>
            </div>

            {{-- Daily activity chart --}}
            @php
                $maxDay = max(array_column($dailyActivity, 'count')) ?: 1;
                $totalLast30 = array_sum(array_column($dailyActivity, 'count'));
                $activeDaysLast30 = count(array_filter($dailyActivity, fn ($d) => $d['count'] > 0));
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-gray-100">Actividad diaria</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Unidades completadas los últimos 30 días</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalLast30 }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">en 30 días</p>
                        </div>
                    </div>

                    @if ($totalLast30 === 0)
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/30 p-8 text-center">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Aún no has completado ninguna unidad en los últimos 30 días.</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Una unidad cuenta cuando terminas los 10 ejercicios.</p>
                            <a href="{{ route('dashboard') }}" wire:navigate class="inline-block mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                Empezar ahora →
                            </a>
                        </div>
                    @else
                        {{-- Dot plot chart: each dot = 1 unit completed --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/30 p-3 sm:p-4">
                            {{-- Counts row above dots --}}
                            <div class="flex gap-[2px] sm:gap-1 mb-1">
                                @foreach ($dailyActivity as $day)
                                    <div class="flex-1 text-center">
                                        @if ($day['count'] > 0)
                                            <span class="text-[9px] sm:text-[10px] font-bold text-indigo-600 dark:text-indigo-400">{{ $day['count'] }}</span>
                                        @else
                                            <span class="text-[9px] sm:text-[10px] text-gray-300 dark:text-gray-600">·</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Dots area --}}
                            <div class="flex gap-[2px] sm:gap-1 items-end h-40 sm:h-48 overflow-hidden border-b-2 border-gray-300 dark:border-gray-600 pb-1">
                                @foreach ($dailyActivity as $day)
                                    @php
                                        $isToday = $day['date']->isToday();
                                    @endphp
                                    <div class="flex-1 flex flex-col-reverse items-center gap-[2px] group relative"
                                         title="{{ $day['count'] }} {{ $day['count'] === 1 ? 'unidad' : 'unidades' }} · {{ $day['date']->format('d M Y') }}">
                                        @if ($day['count'] > 0)
                                            {{-- Tooltip --}}
                                            <div class="absolute bottom-full mb-1 hidden group-hover:block z-10 bg-gray-900 dark:bg-gray-700 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap shadow-lg pointer-events-none">
                                                <span class="font-semibold">{{ $day['count'] }}</span>
                                                {{ $day['count'] === 1 ? 'unidad' : 'unidades' }}
                                                <br>
                                                <span class="text-gray-300">{{ $day['date']->format('d M Y') }}</span>
                                            </div>
                                            @foreach (range(1, $day['count']) as $dotIdx)
                                                <span class="w-2 h-2 rounded-full shrink-0 {{ $isToday ? 'bg-indigo-500 ring-1 ring-indigo-300 dark:ring-indigo-700' : 'bg-indigo-500' }}"></span>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- X axis: show every 5 days --}}
                            <div class="flex gap-[2px] sm:gap-1 mt-1.5">
                                @foreach ($dailyActivity as $i => $day)
                                    <div class="flex-1 text-center">
                                        @if ($i % 5 === 0 || $i === count($dailyActivity) - 1)
                                            <span class="text-[9px] sm:text-[10px] font-medium {{ $day['date']->isToday() ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-gray-500 dark:text-gray-400' }}">
                                                {{ $day['date']->format('d/m') }}
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-center text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-500 mt-1">
                                Cada punto = 1 unidad
                            </p>
                        </div>

                        {{-- Quick stats below the chart --}}
                        <div class="grid grid-cols-3 gap-3 mt-4">
                            <div class="text-center p-2 rounded-md bg-indigo-50 dark:bg-indigo-900/30 ring-1 ring-indigo-200 dark:ring-indigo-800">
                                <div class="text-lg font-bold text-indigo-700 dark:text-indigo-300">{{ $activeDaysLast30 }}</div>
                                <div class="text-[10px] uppercase tracking-wide text-indigo-700/70 dark:text-indigo-400/70">Días activos</div>
                            </div>
                            <div class="text-center p-2 rounded-md bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                                <div class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ $maxDay }}</div>
                                <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Mejor día</div>
                            </div>
                            <div class="text-center p-2 rounded-md bg-amber-50 dark:bg-amber-900/30 ring-1 ring-amber-200 dark:ring-amber-800">
                                <div class="text-lg font-bold text-amber-700 dark:text-amber-300">{{ $activeDaysLast30 > 0 ? number_format($totalLast30 / $activeDaysLast30, 1) : '0' }}</div>
                                <div class="text-[10px] uppercase tracking-wide text-amber-700/70 dark:text-amber-400/70">Promedio/día</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Per-book stats --}}
            @foreach ($books as $book)
                @php
                    $s = $stats[$book->id] ?? null;
                    if (!$s) continue;
                    $palette = $palettes[$book->level] ?? $palettes['Beginner'];
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Book header --}}
                    <div class="{{ $palette['soft'] }} p-5 flex items-center gap-4">
                        <div class="shrink-0 w-12 h-12 rounded-xl {{ $palette['accent'] }} text-white flex items-center justify-center shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 truncate">{{ $book->title }}</h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                @if ($s->hasStarted())
                                    Iniciado: {{ $s->bookProgress->start_date->format('d/m/Y') }}
                                @else
                                    No iniciado
                                @endif
                            </p>
                        </div>
                        @if ($s->isCompleted())
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-500 text-white text-xs font-bold shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Completado
                            </span>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="p-5 space-y-5">

                        {{-- Overall progress bar --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-gray-600 dark:text-gray-400">Progreso general</span>
                                <span class="text-sm font-bold {{ $palette['text'] }}">{{ number_format($s->overallProgress(), 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                <div class="{{ $palette['accent'] }} h-2.5 rounded-full transition-all"
                                     style="width: {{ max($s->overallProgress(), 1) }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $s->totalRepetitions }}/{{ $s->totalPossibleRepetitions() }} repeticiones completadas
                            </p>
                        </div>

                        {{-- Unit stats --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div class="text-center p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto text-emerald-600 dark:text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ $s->unitsMastered }}</p>
                                <p class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Dominadas</p>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 ring-1 ring-amber-200 dark:ring-amber-800">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto text-amber-600 dark:text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ $s->unitsInProgress }}</p>
                                <p class="text-[10px] uppercase tracking-wide text-amber-700/70 dark:text-amber-400/70">En progreso</p>
                            </div>
                            <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 ring-1 ring-gray-200 dark:ring-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto text-gray-500 dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-4a1 1 0 100 2 1 1 0 000-2zm0 4a1 1 0 100 2v3a1 1 0 100 2h1a1 1 0 100-2v-3a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xl font-bold text-gray-700 dark:text-gray-300 mt-1">{{ $s->unitsNotStarted }}</p>
                                <p class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Sin iniciar</p>
                            </div>
                        </div>

                        {{-- Estimated completion (if started and not finished) --}}
                        @if ($s->hasStarted() && $s->estimatedCompletionDate())
                            <div class="p-4 rounded-xl bg-gradient-to-br {{ $palette['soft'] }} border-2 {{ $palette['ring'] }}">
                                <div class="flex items-start gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 h-6 w-6 {{ $palette['text'] }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Fecha estimada de finalización</p>
                                        <p class="text-2xl font-bold {{ $palette['text'] }} mt-1">
                                            {{ $s->estimatedCompletionDate()->format('d/m/Y') }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                            {{ $s->timeRemainingText() }}
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-3 p-2.5 rounded-lg bg-white/60 dark:bg-gray-800/60 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 h-4 w-4 {{ $palette['text'] }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.477.859h4z" />
                                    </svg>
                                    <p class="text-xs italic text-gray-700 dark:text-gray-300">
                                        ¡Sigue practicando! Entre más estudies, más cerca estarás de tu meta.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Days of study + reps/day (if started) --}}
                        @if ($s->hasStarted())
                            <div class="p-4 rounded-xl {{ $palette['soft'] }} ring-1 {{ $palette['ring'] }}">
                                <div class="flex items-center justify-around">
                                    <div class="text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto text-gray-500 dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                        </svg>
                                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $s->daysElapsed() }}</p>
                                        <p class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ $s->daysElapsed() === 1 ? 'Día de estudio' : 'Días de estudio' }}</p>
                                    </div>
                                    <div class="w-px h-12 {{ str_replace('ring-', 'bg-', explode(' ', $palette['ring'])[0]) }}"></div>
                                    <div class="text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mx-auto text-gray-500 dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd" />
                                        </svg>
                                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($s->repetitionsPerDay(), 1) }}/día</p>
                                        <p class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Ritmo</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
