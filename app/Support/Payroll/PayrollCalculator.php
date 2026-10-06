<?php

namespace App\Support\Payroll;

use App\Models\PayrollLine;
use App\Models\StaffContract;

/** Works out GOSI contributions and net pay from a contract and the month's adjustments. */
class PayrollCalculator
{
    /** @return array{employee: float, employer: float} */
    public static function gosi(StaffContract $contract): array
    {
        if (! $contract->gosi_registered) {
            return ['employee' => 0.0, 'employer' => 0.0];
        }
        $config = config('madrasa_payroll.gosi');
        $wage = min($config['max_wage'], max($config['min_wage'], (float) $contract->basic_salary + (float) $contract->housing_allowance));
        $rates = $config[$contract->is_saudi ? 'saudi' : 'non_saudi'];

        return [
            'employee' => round($wage * $rates['employee'] / 100, 2),
            'employer' => round($wage * $rates['employer'] / 100, 2),
        ];
    }

    /** @return array<string, float> line amounts for a fresh month */
    public static function lineFor(StaffContract $contract): array
    {
        $gosi = self::gosi($contract);
        $line = [
            'basic' => (float) $contract->basic_salary, 'housing' => (float) $contract->housing_allowance,
            'transport' => (float) $contract->transport_allowance, 'other' => (float) $contract->other_allowances,
            'additions' => 0.0, 'deductions' => 0.0,
            'gosi_employee' => $gosi['employee'], 'gosi_employer' => $gosi['employer'],
        ];

        return $line + ['net' => self::net($line)];
    }

    /** @param  array<string, float|string>|PayrollLine  $line */
    public static function net(array|PayrollLine $line): float
    {
        $v = fn (string $k) => (float) ($line instanceof PayrollLine ? $line->{$k} : $line[$k]);

        return round($v('basic') + $v('housing') + $v('transport') + $v('other') + $v('additions') - $v('deductions') - $v('gosi_employee'), 2);
    }
}
