<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Schools\CreateSchool;
use App\Enums\DateDisplay;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SchoolResource;
use App\Models\User;
use App\Support\Locale;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    /** Platform admins onboard a new school and name its first admin. */
    public function store(Request $request, CreateSchool $createSchool): SchoolResource
    {
        abort_unless($request->user()->is_platform_admin, 403);

        $data = $request->validate([
            'slug' => ['required', 'string', 'alpha_dash:ascii', 'regex:/^[a-z]/', 'max:60', Rule::unique('schools', 'slug')],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['sometimes', Rule::in(Locale::supported())],
            'date_display' => ['sometimes', Rule::enum(DateDisplay::class)],
            'ministry_code' => ['nullable', 'string', 'max:30'],
            'vat_number' => ['nullable', 'digits:15'],
            'admin_email' => ['required', 'email', Rule::exists('users', 'email')],
        ]);

        $admin = User::query()->where('email', $data['admin_email'])->firstOrFail();
        unset($data['admin_email']);

        return new SchoolResource($createSchool->handle($data, $admin));
    }

    /** The active school (from the X-School header or the only membership). */
    public function current(CurrentSchool $currentSchool): SchoolResource
    {
        return new SchoolResource($currentSchool->get());
    }
}
