<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['guardian_id', 'student_id', 'channel', 'purpose', 'to', 'body', 'status', 'error'])]
class MessageLog extends Model
{
    use BelongsToSchool;
}
