<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            BooksSeeder::class,
            UnitsSeeder::class,
            WordsSeeder::class,
            StoryWordsSeeder::class,
            ExerciseTypesSeeder::class,
        ]);
    }
}
