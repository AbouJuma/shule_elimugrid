<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Global permission catalog. Permissions are NOT team/school-scoped —
 * the string catalog is identical for every tenant; what differs per
 * school is which ROLE (team-scoped) is granted which permissions.
 *
 * Only modules that actually exist and enforce these checks are listed
 * here. As each later phase ships a module (fees, transport, exams,
 * ...) its permissions are added here and to DefaultRoleProvisioner —
 * do not pre-seed permissions for modules that don't exist yet.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Platform (Super Admin only)
            'platform.manage',
            'schools.view',
            'schools.create',
            'schools.update',
            'schools.suspend',
            'schools.activate',

            // Within a school
            'users.view',
            'users.create',
            'users.update',
            'users.suspend',
            'roles.view',
            'roles.manage',

            // Academics (exams/results come with a later phase)
            'academic_years.view',
            'academic_years.manage',
            'classes.view',
            'classes.manage',

            // Admissions
            'students.view',
            'students.create',
            'students.update',
            'students.archive',
            'students.delete',
            'guardians.view',
            'guardians.manage',

            // Attendance
            'attendance.view',
            'attendance.mark',
            // A saved register (class or subject, for a date) locks itself
            // immediately — re-marking it needs this separate permission,
            // deliberately not given to Teacher, so a register can't be
            // silently changed after the fact by whoever marked it.
            'attendance.unlock',
            // Scanning a student's or a staff member's ID marks them
            // present for today — one permission covers both, since it's
            // the same physical gate-check-in action either way.
            'attendance.scan',

            // Staff/teacher attendance — a separate register from
            // students' (see StaffAttendanceService), same locking rule.
            'staff_attendance.view',
            'staff_attendance.mark',
            'staff_attendance.unlock',

            // Exams & results
            'subjects.view',
            'subjects.manage',
            'exams.view',
            'exams.manage',
            'exams.record_marks',
            'exams.publish',

            // Fees & payments
            'bank_accounts.view',
            'bank_accounts.manage',
            'fees.view',
            'fees.manage',
            'fees.invoice',
            'fees.collect',

            // Certificates
            'certificates.view',
            'certificates.issue',
            'certificates.revoke',
            'certificate_templates.manage',

            // Student ID cards
            'id_cards.view',
            'id_cards.issue',
            'id_cards.revoke',
            'id_card_templates.manage',

            // Transport & GPS
            'transport.view',
            'transport.manage',
            'transport.track',

            // Communications / SMS
            'communications.view',
            'communications.send',
            'communications.manage_templates',

            // Reporting
            'reports.view',

            // HR
            'hr.view',
            'hr.manage',
            'leave.approve',

            // Inventory & Procurement
            'inventory.view',
            'inventory.manage',
            'procurement.approve',

            // Library
            'library.view',
            'library.manage',

            // Hostel
            'hostel.view',
            'hostel.manage',

            // Virtual Classroom
            'virtual_classes.view',
            'virtual_classes.manage',

            // Learning Hub: lessons, homework, and auto-graded online exams
            'lessons.view',
            'lessons.manage',
            'homework.view',
            'homework.manage',
            'online_exams.view',
            'online_exams.manage',

            // Termly results & report cards
            'results.view',
            'results.manage',

            // Timetable
            'timetable.view',
            'timetable.manage',

            // Payroll
            'payroll.view',
            'payroll.manage',
            'payroll.disburse',

            // School profile & branding
            'school.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
