<?php

namespace Database\Seeders;

use App\Models\StoryWord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoryWordsSeeder extends Seeder
{
    public function run(): void
    {
        $json = file_get_contents(database_path('seeders/data/cuentoJson.json'));
        $items = json_decode($json, true);

        DB::table('story_words')->truncate();

        $rows = [];
        $now = now();
        foreach ($items as $sw) {
            $rows[] = [
                'unit_id' => $sw['unitID'],
                'order' => $sw['order'],
                'text' => $sw['text'],
                'is_core' => (bool) ($sw['isCore'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StoryWord::insert($chunk);
        }

        $this->command->info('Story words importadas: '.count($rows));
    }
}
