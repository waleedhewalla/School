<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Pay terms of a staff member. Salaries and bank details are sensitive: payroll staff only. */
#[Fillable(['staff_member_id', 'is_saudi', 'hired_on', 'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowances', 'gosi_registered', 'bank', 'iban'])]
#[Hidden(['iban'])]
class StaffContract extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return [
            'is_saudi' => 'boolean', 'gosi_registered' => 'boolean', 'hired_on' => DateOnly::class,
            'basic_salary' => 'decimal:2', 'housing_allowance' => 'decimal:2', 'transport_allowance' => 'decimal:2', 'other_allowances' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Who changed pay and when; the bank account itself is not copied into the log.
        return LogOptions::defaults()->logOnly(['basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowances', 'is_saudi', 'gosi_registered'])->logOnlyDirty();
    }
}
