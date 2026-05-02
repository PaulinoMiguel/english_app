<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryWord extends Model
{
    protected $fillable = [
        'unit_id',
        'order',
        'text',
        'is_core',
    ];

    protected $casts = [
        'is_core' => 'boolean',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
