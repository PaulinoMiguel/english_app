<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renumber: Read 8 → 10, Organize Definition 9 → 8, Organize Example 10 → 9.
 * Read becomes the final exercise (gating point for word mastery).
 */
return new class extends Migration
{
    public function up(): void
    {
        $map = [8 => 10, 9 => 8, 10 => 9];

        // Two-step swap on exercise_types because `number` is unique.
        DB::table('exercise_types')->where('number', 8)->update(['number' => 100]);
        DB::table('exercise_types')->where('number', 9)->update(['number' => 8]);
        DB::table('exercise_types')->where('number', 10)->update(['number' => 9]);
        DB::table('exercise_types')->where('number', 100)->update(['number' => 10]);

        // Remap exercises_completed JSON arrays in unit_progress.
        DB::table('unit_progress')->orderBy('id')->each(function ($row) use ($map) {
            $arr = json_decode($row->exercises_completed ?? '[]', true) ?: [];
            if (! $arr) return;
            $remapped = array_map(fn ($n) => $map[$n] ?? $n, $arr);
            sort($remapped);
            DB::table('unit_progress')->where('id', $row->id)->update([
                'exercises_completed' => json_encode($remapped),
            ]);
        });
    }

    public function down(): void
    {
        $map = [10 => 8, 8 => 9, 9 => 10];

        DB::table('exercise_types')->where('number', 10)->update(['number' => 100]);
        DB::table('exercise_types')->where('number', 8)->update(['number' => 9]);
        DB::table('exercise_types')->where('number', 9)->update(['number' => 10]);
        DB::table('exercise_types')->where('number', 100)->update(['number' => 8]);

        DB::table('unit_progress')->orderBy('id')->each(function ($row) use ($map) {
            $arr = json_decode($row->exercises_completed ?? '[]', true) ?: [];
            if (! $arr) return;
            $remapped = array_map(fn ($n) => $map[$n] ?? $n, $arr);
            sort($remapped);
            DB::table('unit_progress')->where('id', $row->id)->update([
                'exercises_completed' => json_encode($remapped),
            ]);
        });
    }
};
