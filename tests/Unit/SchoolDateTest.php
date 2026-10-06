<?php

namespace Tests\Unit;

use App\Enums\DateDisplay;
use App\Support\Dates\SchoolDate;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SchoolDateTest extends TestCase
{
    public function test_converts_to_umm_al_qura(): void
    {
        // 1 Ramadan 1445 AH began on 11 March 2024 in the Umm al-Qura calendar.
        $date = CarbonImmutable::parse('2024-03-11', 'Asia/Riyadh');

        $this->assertSame(['year' => 1445, 'month' => 9, 'day' => 1], SchoolDate::hijriParts($date));
        $this->assertSame('1 Ramadan 1445 AH', SchoolDate::hijri($date, 'en'));
        $this->assertSame('1 رمضان 1445 هـ', SchoolDate::hijri($date, 'ar'));
    }

    public function test_shows_both_calendars(): void
    {
        $date = CarbonImmutable::parse('2024-03-11', 'Asia/Riyadh');

        $this->assertSame('1 Ramadan 1445 AH — 11 March 2024', SchoolDate::display($date, DateDisplay::Both, 'en'));
        $this->assertSame('11 مارس 2024', SchoolDate::display($date, DateDisplay::Gregorian, 'ar'));
    }

    public function test_converts_hijri_to_gregorian(): void
    {
        $this->assertSame('2024-03-11', SchoolDate::fromHijri(1445, 9, 1)->toDateString());
        $this->assertSame('2019-05-14', SchoolDate::fromHijri(1440, 9, 9)->toDateString());
    }
}
