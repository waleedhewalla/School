<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_normalizes_saudi_mobiles(): void
    {
        foreach (['0551234567', '551234567', '+966551234567', '00966551234567', '055 123 4567', '٠٥٥١٢٣٤٥٦٧'] as $input) {
            $this->assertSame('966551234567', PhoneNumber::normalize($input), $input);
        }
    }

    public function test_rejects_unusable_numbers(): void
    {
        $this->assertNull(PhoneNumber::normalize('966112345678')); // Saudi landline
        $this->assertNull(PhoneNumber::normalize('12345'));
        $this->assertNull(PhoneNumber::normalize(null));
    }
}
