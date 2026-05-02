<x-app-layout>
    @php
        $palette = match($book->level) {
            'Beginner' => ['accent' => 'bg-emerald-500', 'badge' => 'bg-emerald-500 text-white', 'text' => 'text-emerald-600 dark:text-emerald-400'],
            'Elementary' => ['accent' => 'bg-teal-500', 'badge' => 'bg-teal-500 text-white', 'text' => 'text-teal-600 dark:text-teal-400'],
            'Pre-Intermediate' => ['accent' => 'bg-cyan-500', 'badge' => 'bg-cyan-500 text-white', 'text' => 'text-cyan-600 dark:text-cyan-400'],
            'Intermediate' => ['accent' => 'bg-blue-500', 'badge' => 'bg-blue-500 text-white', 'text' => 'text-blue-600 dark:text-blue-400'],
            'Upper-Intermediate' => ['accent' => 'bg-indigo-500', 'badge' => 'bg-indigo-500 text-white', 'text' => 'text-indigo-600 dark:text-indigo-400'],
            'Advanced' => ['accent' => 'bg-purple-500', 'badge' => 'bg-purple-500 text-white', 'text' => 'text-purple-600 dark:text-purple-400'],
            default => ['accent' => 'bg-gray-500', 'badge' => 'bg-gray-500 text-white', 'text' => 'text-gray-600 dark:text-gray-400'],
        };

        $totals = ['mastered' => 0, 'in_progress' => 0, 'not_started' => 0];
        foreach ($units as $u) {
            $r = $progressByUnit->get($u->id)?->repetition_count ?? 0;
            if ($r >= 10) $totals['mastered']++;
            elseif ($r > 0) $totals['in_progress']++;
            else $totals['not_started']++;
        }
        $totalUnits = $units->count();
        $percent = $totalUnits > 0 ? (int) round(($totals['mastered'] / $totalUnits) * 100) : 0;
    @endphp

    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}"
               class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition"
               aria-label="Volver">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
                    {{ $book->title }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $book->level }} · {{ $totalUnits }} unidades</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('flash_message'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
                     class="flex items-center gap-3 p-4 mb-5 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-medium">{{ session('flash_message') }}</span>
                    <button @click="show = false" class="ml-auto shrink-0 text-blue-600 dark:text-blue-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            @endif
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>
                <div class="p-5 sm:p-6 pt-6 sm:pt-7">
                    <div class="flex flex-wrap items-start gap-3 mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $palette['badge'] }} shadow-sm">
                            {{ $book->level }}
                        </span>
                        @if ($book->description)
                            <p class="flex-1 min-w-0 text-sm text-gray-600 dark:text-gray-400">{{ $book->description }}</p>
                        @endif
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-gray-700 dark:text-gray-300">Progreso del libro</span>
                            <span class="font-bold {{ $palette['text'] }}">{{ $percent }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                            <div class="{{ $palette['accent'] }} h-2.5 rounded-full transition-all"
                                 style="width: {{ max($percent, 2) }}%"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 pt-2">
                            <div class="text-center p-2 rounded-md bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                                <div class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ $totals['mastered'] }}</div>
                                <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Dominadas</div>
                            </div>
                            <div class="text-center p-2 rounded-md bg-amber-50 dark:bg-amber-900/30 ring-1 ring-amber-200 dark:ring-amber-800">
                                <div class="text-lg font-bold text-amber-700 dark:text-amber-300">{{ $totals['in_progress'] }}</div>
                                <div class="text-[10px] uppercase tracking-wide text-amber-700/70 dark:text-amber-400/70">En curso</div>
                            </div>
                            <div class="text-center p-2 rounded-md bg-gray-50 dark:bg-gray-700/50 ring-1 ring-gray-200 dark:ring-gray-700">
                                <div class="text-lg font-bold text-gray-700 dark:text-gray-300">{{ $totals['not_started'] }}</div>
                                <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Nuevas</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @php
                $lockedCount = collect($availability)->filter(fn ($a) => ! $a['available'])->count();
                $activeCount = $units->count() - $lockedCount;
            @endphp

            <div x-data="{ showAll: false }">
                <div class="flex items-center justify-between mb-3 px-1">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Unidades
                        <span class="text-gray-400 dark:text-gray-500 font-normal normal-case ml-1" x-text="showAll ? '({{ $units->count() }} totales)' : '({{ $activeCount }} activas)'">({{ $activeCount }} activas)</span>
                    </h3>
                    @if ($lockedCount > 0)
                        <button @click="showAll = ! showAll" type="button"
                                class="inline-flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                            <span x-text="showAll ? 'Solo activas' : 'Ver todas'">Ver todas</span>
                            <span class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                  :class="showAll ? '{{ $palette['accent'] }}' : 'bg-gray-300 dark:bg-gray-600'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                      :class="showAll ? 'translate-x-4' : 'translate-x-1'"></span>
                            </span>
                        </button>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    @foreach ($units as $unit)
                    @php
                        $p = $progressByUnit->get($unit->id);
                        $reps = $p?->repetition_count ?? 0;
                        $exDone = is_array($p?->exercises_completed) ? count($p->exercises_completed) : 0;
                        $exPercent = (int) round(($exDone / 10) * 100);

                        if ($reps >= 10) {
                            $circleBg = 'bg-emerald-500';
                            $circleText = 'text-white';
                            $statusLabel = 'Dominada';
                            $statusClass = 'text-emerald-700 dark:text-emerald-300';
                            $barColor = 'bg-emerald-500';
                        } elseif ($reps > 0) {
                            $circleBg = 'bg-amber-500';
                            $circleText = 'text-white';
                            $statusLabel = 'En curso';
                            $statusClass = 'text-amber-700 dark:text-amber-300';
                            $barColor = 'bg-amber-500';
                        } else {
                            $circleBg = 'bg-gray-200 dark:bg-gray-700';
                            $circleText = 'text-gray-700 dark:text-gray-300';
                            $statusLabel = 'Nueva';
                            $statusClass = 'text-gray-600 dark:text-gray-400';
                            $barColor = $palette['accent'];
                        }

                        $avail = $availability[$unit->id] ?? ['available' => true, 'days_until' => 0, 'next_review' => null];
                        $isLocked = ! $avail['available'];

                        if ($isLocked) {
                            $statusLabel = 'En descanso';
                            $statusClass = 'text-blue-700 dark:text-blue-300';
                        }
                    @endphp
                    @if ($isLocked)
                        <div x-show="showAll" class="block bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border border-gray-200 dark:border-gray-700 opacity-70 cursor-not-allowed">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="shrink-0 w-11 h-11 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $unit->title }}</h3>
                                    <p class="text-xs font-medium text-blue-700 dark:text-blue-300 mt-0.5">
                                        En descanso · {{ $avail['days_until'] === 1 ? 'vuelve mañana' : 'vuelve en '.$avail['days_until'].' días' }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                <span class="font-semibold {{ $palette['text'] }}">{{ $reps }}</span>
                                {{ $reps === 1 ? 'repetición completada' : 'repeticiones completadas' }}
                                @if ($avail['next_review'])
                                    · <span class="font-medium">{{ $avail['next_review']->format('d/m/Y') }}</span>
                                @endif
                            </div>
                        </div>
                    @else
                        <a href="{{ route('units.show', $unit) }}"
                           class="group block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-all p-4 border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="shrink-0 w-11 h-11 rounded-full {{ $circleBg }} flex items-center justify-center shadow-sm">
                                    <span class="{{ $circleText }} font-bold text-sm">{{ $unit->unit_number }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        {{ $unit->title }}
                                    </h3>
                                    <p class="text-xs font-medium {{ $statusClass }} mt-0.5">{{ $statusLabel }}</p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 h-5 w-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                    <span>Ejercicios</span>
                                    <span class="font-semibold">{{ $exDone }}/10</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                    <div class="{{ $barColor }} h-1.5 rounded-full transition-all"
                                         style="width: {{ max($exPercent, 2) }}%"></div>
                                </div>
                                @if ($reps > 0)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-0.5">
                                        <span class="font-semibold {{ $palette['text'] }}">{{ $reps }}</span>
                                        {{ $reps === 1 ? 'repetición' : 'repeticiones' }}
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endif
                @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
