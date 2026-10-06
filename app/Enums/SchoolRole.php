<?php

namespace App\Enums;

/**
 * Roles every school starts with. Schools may edit the permissions of
 * these roles or add their own; these are only the defaults.
 */
enum SchoolRole: string
{
    case SchoolAdmin = 'school_admin';
    case Principal = 'principal';
    case Registrar = 'registrar';
    case Teacher = 'teacher';
    case Accountant = 'accountant';
    case Guardian = 'guardian';
    case Student = 'student';

    /** @return list<string> */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SchoolAdmin => Permission::all(),
            self::Principal => [
                Permission::AcademicStructureView, Permission::AcademicStructureManage,
                Permission::StudentsView, Permission::StudentsManage, Permission::StaffView,
                Permission::AttendanceView, Permission::AttendanceManage,
                Permission::GradesView, Permission::AuditView, Permission::TimetableManage,
            ],
            self::Registrar => [
                Permission::AcademicStructureView, Permission::StudentsView,
                Permission::StudentsManage, Permission::StaffView,
                Permission::AttendanceView, Permission::AttendanceManage,
            ],
            // Teachers see and take registers only for sections they teach (SectionPolicy).
            self::Teacher => [
                Permission::AcademicStructureView, Permission::StudentsView,
                Permission::AttendanceRecord,
                Permission::GradesView, Permission::GradesRecord,
            ],
            self::Accountant => [
                Permission::StudentsView, Permission::FinanceView, Permission::FinanceManage,
            ],
            self::Guardian, self::Student => [],
        };
    }
}
