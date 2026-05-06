<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordMastery extends Model
{
    protected $table = 'word_mastery';

    protected $fillable = [
        'user_id',
        'word_id',
        'marked_at_rep',
        'expires_at_rep',
        'backoff_level',
    ];

    protected $casts = [
        'marked_at_rep' => 'integer',
        'expires_at_rep' => 'integer',
        'backoff_level' => 'integer',
    ];

    public const BACKOFF_INTERVALS = [3, 5, 8];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    public static function intervalForLevel(int $level): int
    {
        return self::BACKOFF_INTERVALS[min($level, count(self::BACKOFF_INTERVALS) - 1)];
    }
}
