<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class BooksSeeder extends Seeder
{
    public function run(): void
    {
        $json = file_get_contents(database_path('seeders/data/books.json'));
        $books = json_decode($json, true);

        foreach ($books as $i => $b) {
            Book::updateOrCreate(
                ['id' => $b['bookId']],
                [
                    'title' => $b['title'],
                    'level' => $b['level'],
                    'description' => $b['description'] ?? null,
                    'total_units' => $b['totalUnits'] ?? 0,
                    'order' => $i + 1,
                ],
            );
        }

        $this->command->info('Books importados: '.count($books));
    }
}
