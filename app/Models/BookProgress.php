<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookProgress extends Model
{
    protected $table = 'book_progress';

    protected $fillable = [
        'user_id',
        'book_id',
        'start_date',
        'last_activity',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'last_activity' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
