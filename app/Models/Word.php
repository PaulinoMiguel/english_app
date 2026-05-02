<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Word extends Model
{
    protected $fillable = [
        'unit_id',
        'text',
        'type',
        'phonetic',
        'translation',
        'definition',
        'example',
        'audio_file',
        'definition_audio',
        'example_audio',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function getAudioUrlAttribute(): ?string
    {
        return $this->audio_file ? config('app.audio_base_url').'/'.$this->audio_file : null;
    }

    public function getDefinitionAudioUrlAttribute(): ?string
    {
        return $this->definition_audio ? config('app.audio_base_url').'/'.$this->definition_audio : null;
    }

    public function getExampleAudioUrlAttribute(): ?string
    {
        return $this->example_audio ? config('app.audio_base_url').'/'.$this->example_audio : null;
    }
}
