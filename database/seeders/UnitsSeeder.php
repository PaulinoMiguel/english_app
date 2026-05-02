<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $json = file_get_contents(database_path('seeders/data/unit.json'));
        $units = json_decode($json, true);

        foreach ($units as $u) {
            Unit::updateOrCreate(
                ['id' => $u['unitId']],
                [
                    'book_id' => $u['bookId'],
                    'unit_number' => $u['unitNumber'],
                    'title' => $u['title'],
                ],
            );
        }

        $this->command->info('Units importadas: '.count($units));
    }
}
