<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One month's payroll: draft while lines are adjusted, then approved and locked. */
#[Fillable(['month', 'status', 'created_by', 'approved_by', 'approved_at'])]
class PayrollRun extends Model
{
    use BelongsToSchool;

    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    /** @return HasMany<PayrollLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }
}
