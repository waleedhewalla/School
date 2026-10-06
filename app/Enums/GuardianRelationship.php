<?php

namespace App\Enums;

enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Brother = 'brother';
    case Sister = 'sister';
    case Grandparent = 'grandparent';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Other = 'other';
}
