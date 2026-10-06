<?php

namespace App\Enums;

enum AttendanceKind: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';
}
