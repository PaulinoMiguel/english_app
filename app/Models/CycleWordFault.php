<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleWordFault extends Model
{
    protected $table = 'unit_cycle_word_faults';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'unit_id',
        'word_id',
        'repetition_count',
        'exercise_number',
        'faulted_at',
    ];

    protected $casts = [
        'repetition_count' => 'integer',
        'exercise_number' => 'integer',
        'faulted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
