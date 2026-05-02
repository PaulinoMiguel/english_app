<?php

namespace Database\Seeders;

use App\Models\Word;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WordsSeeder extends Seeder
{
    public function run(): void
    {
        $json = file_get_contents(database_path('seeders/data/wold.json'));
        $words = json_decode($json, true);

        DB::table('words')->truncate();

        $rows = [];
        $now = now();
        foreach ($words as $w) {
            $phonetic = $w['phonetic'] ?? null;
            $rows[] = [
                'unit_id' => $w['unit'],
                'text' => $w['text'],
                'type' => $w['type'] ?? null,
                'phonetic' => $phonetic === 'none' ? null : $phonetic,
                'translation' => $w['traslation'] ?? null,
                'definition' => $w['definition'] ?? null,
                'example' => $w['example'] ?? null,
                'audio_file' => $w['audioUrl'] ?? null,
                'definition_audio' => $w['definitionUrl'] ?? null,
                'example_audio' => $w['exampleUrl'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Word::insert($chunk);
        }

        $this->command->info('Words importadas: '.count($rows));
    }
}
