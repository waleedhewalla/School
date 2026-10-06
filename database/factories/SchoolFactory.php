<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name_ar' => 'مدارس '.$name,
            'name_en' => $name.' Schools',
            'country' => 'SA',
            'timezone' => 'Asia/Riyadh',
            'default_locale' => 'ar',
            'date_display' => 'both',
            'status' => 'active',
        ];
    }
}
