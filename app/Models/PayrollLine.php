<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payroll_run_id', 'staff_member_id', 'basic', 'housing', 'transport', 'other', 'additions', 'deductions', 'note', 'gosi_employee', 'gosi_employer', 'net'])]
class PayrollLine extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return collect(['basic', 'housing', 'transport', 'other', 'additions', 'deductions', 'gosi_employee', 'gosi_employer', 'net'])
            ->mapWithKeys(fn ($c) => [$c => 'decimal:2'])->all();
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function gross(): float
    {
        return round((float) $this->basic + (float) $this->housing + (float) $this->transport + (float) $this->other + (float) $this->additions, 2);
    }
}
