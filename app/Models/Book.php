<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = [
        'title',
        'level',
        'description',
        'total_units',
        'order',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('unit_number');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(BookProgress::class);
    }
}
