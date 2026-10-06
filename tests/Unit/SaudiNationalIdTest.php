<?php

namespace Tests\Unit;

use App\Rules\SaudiNationalId;
use PHPUnit\Framework\TestCase;

class SaudiNationalIdTest extends TestCase
{
    public function test_accepts_valid_ids_and_iqamas(): void
    {
        $this->assertTrue(SaudiNationalId::isValid('1000000008'));
        $this->assertTrue(SaudiNationalId::isValid('2000000006'));
    }

    public function test_rejects_bad_check_digit_prefix_or_length(): void
    {
        $this->assertFalse(SaudiNationalId::isValid('1000000009'));
        $this->assertFalse(SaudiNationalId::isValid('3000000004'));
        $this->assertFalse(SaudiNationalId::isValid('100000000'));
        $this->assertFalse(SaudiNationalId::isValid('10000000a8'));
    }
}
