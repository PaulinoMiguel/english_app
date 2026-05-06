<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_mastery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('marked_at_rep');
            $table->unsignedInteger('expires_at_rep');
            $table->unsignedTinyInteger('backoff_level')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'word_id']);
            $table->index(['user_id', 'expires_at_rep']);
        });

        Schema::create('unit_cycle_word_faults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('repetition_count');
            $table->unsignedTinyInteger('exercise_number');
            $table->timestamp('faulted_at')->useCurrent();

            $table->unique(['user_id', 'unit_id', 'word_id', 'repetition_count', 'exercise_number'], 'cycle_word_faults_unique');
            $table->index(['user_id', 'unit_id', 'repetition_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_cycle_word_faults');
        Schema::dropIfExists('word_mastery');
    }
};
