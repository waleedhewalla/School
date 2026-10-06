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
    case Counselor = 'counselor';
    case Nurse = 'nurse';
    case Librarian = 'librarian';
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
                Permission::GradesView, Permission::GradesManage, Permission::AuditView, Permission::TimetableManage, Permission::AnnouncementsManage, Permission::AdmissionsManage, Permission::StaffManage,
                Permission::HomeworkAssign, Permission::BehaviourRecord, Permission::BehaviourManage,
                Permission::ClinicManage, Permission::LibraryManage, Permission::TransportManage, Permission::InventoryManage,
            ],
            self::Registrar => [
                Permission::AcademicStructureView, Permission::StudentsView,
                Permission::StudentsManage, Permission::StaffView,
                Permission::AttendanceView, Permission::AttendanceManage, Permission::AdmissionsManage,
                Permission::TransportManage,
            ],
            // Teachers see and take registers only for sections they teach (SectionPolicy).
            self::Teacher => [
                Permission::AcademicStructureView, Permission::StudentsView,
                Permission::AttendanceRecord,
                Permission::GradesView, Permission::GradesRecord,
                Permission::HomeworkAssign, Permission::BehaviourRecord,
            ],
            // Student counsellor (المرشد الطلابي): behaviour and attendance follow-up for every student.
            self::Counselor => [
                Permission::AcademicStructureView, Permission::StudentsView, Permission::AttendanceView,
                Permission::BehaviourRecord, Permission::BehaviourManage,
            ],
            self::Accountant => [
                Permission::StudentsView, Permission::FinanceView, Permission::FinanceManage, Permission::InventoryManage,
                Permission::StaffView, Permission::PayrollManage,
            ],
            // School nurse: health records and clinic visits.
            self::Nurse => [
                Permission::AcademicStructureView, Permission::StudentsView, Permission::ClinicManage,
            ],
            self::Librarian => [
                Permission::AcademicStructureView, Permission::StudentsView, Permission::LibraryManage,
            ],
            self::Guardian, self::Student => [],
        };
    }
}
