<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Subject;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Subject|null $subject */
        $subject = $this->route('subject');
        $creating = $subject === null;

        return [
            'code' => [
                Rule::requiredIf($creating), 'string', 'alpha_dash:ascii', 'max:30',
                Rule::unique('subjects', 'code')
                    ->where('school_id', app(CurrentSchool::class)->id())
                    ->ignore($subject?->getKey()),
            ],
            'sequence' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'name_ar' => [Rule::requiredIf($creating), 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
        ];
    }
}
