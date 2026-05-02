<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookProgress;
use App\Models\Unit;
use App\Models\UnitCompletionLog;
use App\Models\UnitProgress;
use App\Models\User;
use Illuminate\Console\Command;

class SeedDemoActivity extends Command
{
    protected $signature = 'app:seed-demo-activity
                            {--user=1 : ID del usuario a poblar}
                            {--days=30 : Cuántos días hacia atrás simular}
                            {--reset : Borrar progreso/logs existentes del usuario antes de poblar}';

    protected $description = 'Genera datos de actividad simulada (logs y progreso) para probar la gráfica';

    public function handle(): int
    {
        $userId = (int) $this->option('user');
        $days = (int) $this->option('days');
        $reset = (bool) $this->option('reset');

        $user = User::find($userId);
        if (! $user) {
            $this->error("Usuario {$userId} no encontrado.");
            return self::FAILURE;
        }

        if ($reset) {
            $this->warn('Borrando progreso y logs existentes del usuario...');
            UnitCompletionLog::where('user_id', $userId)->delete();
            UnitProgress::where('user_id', $userId)->delete();
            BookProgress::where('user_id', $userId)->delete();
        }

        // Pesos por libro: los primeros libros tienen más actividad simulada
        $bookWeights = [1 => 5, 2 => 3, 3 => 2, 4 => 1, 5 => 1, 6 => 1];

        $books = Book::orderBy('order')->get();
        $unitsByBook = [];
        foreach ($books as $book) {
            $unitsByBook[$book->id] = Unit::where('book_id', $book->id)->pluck('id')->all();
        }

        // Construir pool de unit_ids según pesos
        $unitPool = [];
        foreach ($bookWeights as $bookId => $weight) {
            if (empty($unitsByBook[$bookId] ?? [])) continue;
            for ($i = 0; $i < $weight; $i++) {
                $unitPool = array_merge($unitPool, $unitsByBook[$bookId]);
            }
        }

        // Crear book_progress.start_date para libros con peso > 1, hace $days días
        foreach ($bookWeights as $bookId => $weight) {
            if ($weight <= 1) continue;
            BookProgress::updateOrCreate(
                ['user_id' => $userId, 'book_id' => $bookId],
                [
                    'start_date' => now()->subDays($days - 1)->startOfDay(),
                    'last_activity' => now(),
                ],
            );
        }

        // Distribución diaria: algunos días 0, otros con 2-12 unidades
        // Patrón aleatorio realista: ~70% días activos, con variación
        $totalCreated = 0;
        $unitRepCount = []; // [unit_id => count] para incrementar repetition_count

        for ($d = $days - 1; $d >= 0; $d--) {
            $date = now()->subDays($d);

            // 30% chance de día sin actividad
            if (mt_rand(1, 100) <= 30) continue;

            // Cantidad por día: distribución sesgada hacia 2-5
            $r = mt_rand(1, 100);
            if ($r <= 50) {
                $count = mt_rand(2, 5);
            } elseif ($r <= 85) {
                $count = mt_rand(6, 9);
            } else {
                $count = mt_rand(10, 14);
            }

            for ($i = 0; $i < $count; $i++) {
                $unitId = $unitPool[array_rand($unitPool)];

                // Hora del día: 70% mañana/tarde, 30% noche
                $hour = mt_rand(1, 100) <= 70 ? mt_rand(8, 21) : mt_rand(0, 23);
                $minute = mt_rand(0, 59);
                $completedAt = $date->copy()->setTime($hour, $minute, mt_rand(0, 59));

                UnitCompletionLog::create([
                    'user_id' => $userId,
                    'unit_id' => $unitId,
                    'completed_at' => $completedAt,
                ]);

                $unitRepCount[$unitId] = ($unitRepCount[$unitId] ?? 0) + 1;
                $totalCreated++;
            }
        }

        // Aplicar el conteo a unit_progress
        foreach ($unitRepCount as $unitId => $count) {
            $progress = UnitProgress::firstOrNew(['user_id' => $userId, 'unit_id' => $unitId]);
            $progress->repetition_count = ($progress->repetition_count ?? 0) + $count;
            $progress->last_review = now();
            $progress->exercises_completed = []; // reseteado tras última repetición
            $progress->save();
        }

        $this->info("Creados {$totalCreated} unit_completion_logs distribuidos en {$days} días.");
        $this->info('Unidades únicas afectadas: '.count($unitRepCount));
        $this->info('Recarga /statistics para ver los datos.');

        return self::SUCCESS;
    }
}
