<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->string('type', 20)->nullable();
            $table->string('phonetic')->nullable();
            $table->text('translation')->nullable();
            $table->text('definition')->nullable();
            $table->text('example')->nullable();
            $table->string('audio_file')->nullable();
            $table->string('definition_audio')->nullable();
            $table->string('example_audio')->nullable();
            $table->timestamps();

            $table->index('unit_id');
            $table->index('text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
