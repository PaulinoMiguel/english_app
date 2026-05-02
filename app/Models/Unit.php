<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = [
        'book_id',
        'unit_number',
        'title',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }

    public function storyWords(): HasMany
    {
        return $this->hasMany(StoryWord::class)->orderBy('order');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UnitProgress::class);
    }
}
