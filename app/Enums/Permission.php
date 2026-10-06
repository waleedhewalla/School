<?php

namespace App\Enums;

/**
 * Permission names, kept as constants so they are greppable and typo-safe.
 * Guardians and students get no staff permissions; their access is decided
 * by ownership (their own child / own record) in policies instead.
 */
final class Permission
{
    public const SchoolManage = 'school.manage';

    public const MembersManage = 'members.manage';

    public const AcademicStructureView = 'academic-structure.view';

    public const AcademicStructureManage = 'academic-structure.manage';

    public const StudentsView = 'students.view';

    public const StudentsManage = 'students.manage';

    public const StaffView = 'staff.view';

    public const StaffManage = 'staff.manage';

    public const AttendanceView = 'attendance.view';

    /** Record attendance for sections the user teaches. */
    public const AttendanceRecord = 'attendance.record';

    /** Record or correct attendance for any section. */
    public const AttendanceManage = 'attendance.manage';

    public const GradesView = 'grades.view';

    /** Enter marks for subjects the user teaches, while the term is open. */
    public const GradesRecord = 'grades.record';

    /** Set up assessments and scales, correct any mark, open/close entry, publish results. */
    public const GradesManage = 'grades.manage';

    public const FinanceView = 'finance.view';

    public const FinanceManage = 'finance.manage';

    public const TimetableManage = 'timetable.manage';

    public const AnnouncementsManage = 'announcements.manage';

    public const AdmissionsManage = 'admissions.manage';

    /** Post homework for subjects the user teaches (or any, with academic-structure.manage). */
    public const HomeworkAssign = 'homework.assign';

    /** Record behaviour for students the user teaches. */
    public const BehaviourRecord = 'behaviour.record';

    /** Record behaviour for any student and edit the behaviour categories. */
    public const BehaviourManage = 'behaviour.manage';

    /** Health records and clinic visits (sensitive: nurse and principal only by default). */
    public const ClinicManage = 'clinic.manage';

    public const LibraryManage = 'library.manage';

    public const TransportManage = 'transport.manage';

    public const InventoryManage = 'inventory.manage';

    public const AuditView = 'audit.view';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
