<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AcademicYear;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var AcademicYear|null $year */
        $year = $this->route('academic_year');
        $creating = $year === null;

        return [
            'name' => [
                Rule::requiredIf($creating), 'string', 'max:50',
                Rule::unique('academic_years', 'name')
                    ->where('school_id', app(CurrentSchool::class)->id())
                    ->ignore($year?->getKey()),
            ],
            'starts_on' => [Rule::requiredIf($creating), 'date'],
            'ends_on' => [Rule::requiredIf($creating), 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
            'terms' => [Rule::prohibitedIf(! $creating), 'array', 'max:4'],
            'terms.*.name_ar' => ['required', 'string', 'max:100'],
            'terms.*.name_en' => ['nullable', 'string', 'max:100'],
            'terms.*.starts_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'terms.*.ends_on' => ['required', 'date', 'after:terms.*.starts_on', 'before_or_equal:ends_on'],
        ];
    }
}
