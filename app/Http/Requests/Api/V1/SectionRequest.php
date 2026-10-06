<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Section;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Section|null $section */
        $section = $this->route('section');
        $creating = $section === null;

        return [
            'academic_year_id' => [Rule::requiredIf($creating), 'integer', ExistsInCurrentSchool::in('academic_years')],
            'grade_level_id' => [Rule::requiredIf($creating), 'integer', ExistsInCurrentSchool::inTable('grade_levels')],
            'campus_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('campuses')],
            'name' => [
                Rule::requiredIf($creating), 'string', 'max:30',
                Rule::unique('sections', 'name')
                    ->where('academic_year_id', $this->input('academic_year_id', $section?->academic_year_id))
                    ->where('grade_level_id', $this->input('grade_level_id', $section?->grade_level_id))
                    ->ignore($section?->getKey()),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
