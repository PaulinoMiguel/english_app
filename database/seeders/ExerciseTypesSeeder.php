<?php

namespace Database\Seeders;

use App\Models\ExerciseType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExerciseTypesSeeder extends Seeder
{
    public function run(): void
    {
        $json = file_get_contents(database_path('seeders/data/ejercice_name.json'));
        $items = json_decode($json, true);

        foreach ($items as $e) {
            ExerciseType::updateOrCreate(
                ['number' => $e['numero']],
                [
                    'name' => $e['nombre'],
                    'slug' => Str::slug($e['nombre']),
                ],
            );
        }

        $this->command->info('Exercise types importados: '.count($items));
    }
}
