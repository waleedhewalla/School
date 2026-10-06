<?php

namespace Tests\Unit;

use App\Support\ArabicName;
use PHPUnit\Framework\TestCase;

class ArabicNameTest extends TestCase
{
    public function test_splits_four_part_names_keeping_compounds_together(): void
    {
        $this->assertSame(
            ['first' => 'عبد الرحمن', 'father' => 'محمد', 'grandfather' => 'عبد الله', 'family' => 'آل سعود'],
            ArabicName::split('عبد الرحمن  محمد عبد الله آل سعود'),
        );
    }

    public function test_short_and_long_names(): void
    {
        $this->assertSame(['first' => 'سارة', 'father' => null, 'grandfather' => null, 'family' => 'العتيبي'], ArabicName::split('سارة العتيبي'));
        $this->assertSame(['first' => 'ريم', 'father' => 'خالد', 'grandfather' => null, 'family' => 'العتيبي'], ArabicName::split('ريم خالد العتيبي'));
        $this->assertSame('بن سعيد الغامدي', ArabicName::split('علي محمد أحمد بن سعيد الغامدي')['family']);
    }

    public function test_normalize_for_matching(): void
    {
        $this->assertSame(ArabicName::normalize('الصف الأول الابتدائي'), ArabicName::normalize('الصف الاول الإبتدائى'));
        $this->assertSame('اسم ولي الامر', ArabicName::normalize(' اسم وليّ الأمر '));
    }
}
