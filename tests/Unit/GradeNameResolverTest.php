<?php

namespace Tests\Unit;

use App\Models\GradeLevel;
use App\Models\Stage;
use App\Support\Import\GradeNameResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GradeNameResolverTest extends TestCase
{
    private function resolver(): GradeNameResolver
    {
        $grades = collect();
        foreach (config('madrasa_stages') as $s => $stage) {
            $model = (new Stage(['code' => $stage['code'], 'sequence' => $s + 1]))->forceFill(['id' => $s + 1]);
            foreach ($stage['grades'] as $g => [$ar, $en]) {
                $grades->push((new GradeLevel(['name_ar' => $ar, 'name_en' => $en, 'sequence' => $g + 1]))
                    ->forceFill(['id' => ($s + 1) * 10 + $g + 1])->setRelation('stage', $model));
            }
        }

        return new GradeNameResolver($grades);
    }

    public static function names(): array
    {
        return [
            ['الصف الأول الابتدائي', 'الصف الأول الابتدائي'],
            ['الاول الابتدائي', 'الصف الأول الابتدائي'],
            ['أول ابتدائي', 'الصف الأول الابتدائي'],
            ['ثالث متوسط', 'الصف الثالث المتوسط'],
            ['الثانية الثانوية', 'الصف الثاني الثانوي'],
            ['١ متوسط', 'الصف الأول المتوسط'],
            ['الصف 7', 'الصف الأول المتوسط'],
            ['12', 'الصف الثالث الثانوي'],
            ['Grade 4', 'الصف الرابع الابتدائي'],
            ['روضة ثانية', 'المستوى الثاني'],
            ['المستوى الثالث', 'المستوى الثالث'],
        ];
    }

    #[DataProvider('names')]
    public function test_resolves_saudi_grade_names(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->resolver()->resolve($input)?->name_ar);
    }

    public function test_unknown_grades(): void
    {
        $this->assertNull($this->resolver()->resolve('الصف العاشر الإعدادي'));
        $this->assertNull($this->resolver()->resolve(''));
        $this->assertNull($this->resolver()->resolve('سابع متوسط'));
    }
}
