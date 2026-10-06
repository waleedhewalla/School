<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A quiz question. `options` holds the choices (single, multiple); `correct`
 * holds choice indexes, [true]/[false], or accepted short answers.
 */
#[Fillable(['quiz_id', 'type', 'body', 'options', 'correct', 'points', 'sequence'])]
class QuizQuestion extends Model
{
    use BelongsToSchool;

    public const TYPES = ['single', 'multiple', 'true_false', 'short'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct' => 'array', 'points' => 'float'];
    }
}
