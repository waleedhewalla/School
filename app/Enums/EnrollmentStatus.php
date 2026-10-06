<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case Withdrawn = 'withdrawn';
}
