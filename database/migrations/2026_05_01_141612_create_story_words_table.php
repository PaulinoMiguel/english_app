<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('text');
            $table->boolean('is_core')->default(false);
            $table->timestamps();

            $table->index(['unit_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_words');
    }
};
