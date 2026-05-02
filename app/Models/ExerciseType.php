<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseType extends Model
{
    protected $fillable = [
        'number',
        'name',
        'slug',
    ];
}
