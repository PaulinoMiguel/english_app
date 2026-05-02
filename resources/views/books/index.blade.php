<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Mis libros
        </h2>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                Hola, {{ auth()->user()->name }}. Elige un libro para continuar tu aprendizaje.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                @foreach ($books as $book)
                    @php
                        $s = $stats[$book->id];
                        $palette = match($book->level) {
                            'Beginner' => ['accent' => 'bg-emerald-500', 'badge' => 'bg-emerald-500 text-white'],
                            'Elementary' => ['accent' => 'bg-teal-500', 'badge' => 'bg-teal-500 text-white'],
                            'Pre-Intermediate' => ['accent' => 'bg-cyan-500', 'badge' => 'bg-cyan-500 text-white'],
                            'Intermediate' => ['accent' => 'bg-blue-500', 'badge' => 'bg-blue-500 text-white'],
                            'Upper-Intermediate' => ['accent' => 'bg-indigo-500', 'badge' => 'bg-indigo-500 text-white'],
                            'Advanced' => ['accent' => 'bg-purple-500', 'badge' => 'bg-purple-500 text-white'],
                            default => ['accent' => 'bg-gray-500', 'badge' => 'bg-gray-500 text-white'],
                        };
                    @endphp
                    <a href="{{ route('books.show', $book) }}"
                       class="group relative block bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-lg transition-all p-5 sm:p-6 border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-1.5 {{ $palette['accent'] }}"></div>

                        <div class="flex items-start justify-between mb-3 mt-1">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $palette['badge'] }} shadow-sm">
                                {{ $book->level }}
                            </span>
                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                {{ $s['total'] }} unidades
                            </span>
                        </div>

                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                            {{ $book->title }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 line-clamp-2">
                            {{ $book->description }}
                        </p>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Progreso</span>
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $s['percent'] }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                <div class="{{ $palette['accent'] }} h-2.5 rounded-full transition-all"
                                     style="width: {{ max($s['percent'], 2) }}%"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-2 pt-1">
                                <div class="text-center p-2 rounded-md bg-emerald-50 dark:bg-emerald-900/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                                    <div class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ $s['mastered'] }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70">Dominadas</div>
                                </div>
                                <div class="text-center p-2 rounded-md bg-amber-50 dark:bg-amber-900/30 ring-1 ring-amber-200 dark:ring-amber-800">
                                    <div class="text-lg font-bold text-amber-700 dark:text-amber-300">{{ $s['in_progress'] }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-amber-700/70 dark:text-amber-400/70">En curso</div>
                                </div>
                                <div class="text-center p-2 rounded-md bg-gray-50 dark:bg-gray-700/50 ring-1 ring-gray-200 dark:ring-gray-700">
                                    <div class="text-lg font-bold text-gray-700 dark:text-gray-300">{{ $s['not_started'] }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-400">Nuevas</div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
