<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('unit_number');
            $table->string('title');
            $table->timestamps();

            $table->unique(['book_id', 'unit_number']);
            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
