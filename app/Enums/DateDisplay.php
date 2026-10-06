<?php

namespace App\Enums;

enum DateDisplay: string
{
    case Gregorian = 'gregorian';
    case Hijri = 'hijri';
    case Both = 'both';
}
