<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['grading_scale_id', 'min_percent', 'label_ar', 'label_en'])]
class GradingBand extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['min_percent' => 'float'];
    }

    protected function label(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'en' && filled($this->label_en) ? $this->label_en : $this->label_ar);
    }
}
