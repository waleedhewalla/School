<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['term_id', 'student_id', 'comment', 'author_id'])]
class ReportCardComment extends Model
{
    use BelongsToSchool;
}
