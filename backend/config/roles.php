<?php

return [

    /*
     * Standard role names provisioned automatically for every new school
     * (see App\Services\DefaultRoleProvisioner). Schools can rename,
     * remove, or add further custom roles afterward — this list only
     * seeds a sensible starting point per spec section 8.
     *
     * Only 'School Administrator', 'Teacher', 'Accountant', 'Transport
     * Manager', 'Driver', 'Parent', 'HR', 'Storekeeper', 'Librarian',
     * 'Warden', and 'Receptionist' are granted real permissions at
     * provisioning time, for modules that actually exist and enforce
     * them. The remaining role names are placeholders with zero
     * permissions until their owning module ships (Student, Principal) —
     * granting permissions for modules that don't exist yet would
     * misrepresent what the system enforces. 'Receptionist' is the
     * newest activation, for the gate ID-scanner (a school's front desk
     * is the obvious owner of "who's arrived today").
     */
    'default_school_roles' => [
        'School Administrator',
        'Principal',
        'Teacher',
        'Accountant',
        'HR',
        'Librarian',
        'Storekeeper',
        'Transport Manager',
        'Driver',
        'Receptionist',
        'Parent',
        'Student',
        'Warden',
    ],

    'super_admin_role' => 'Super Admin',

    /*
     * Permissions granted to the "School Administrator" role at
     * provisioning time. Extend this as each module ships — it's the
     * single place that has to stay in sync with database/seeders/
     * PermissionSeeder.php's catalog.
     */
    'school_administrator_permissions' => [
        'users.view',
        'users.create',
        'users.update',
        'users.suspend',
        'roles.view',
        'roles.manage',
        'academic_years.view',
        'academic_years.manage',
        'classes.view',
        'classes.manage',
        'students.view',
        'students.create',
        'students.update',
        'students.archive',
        'students.delete',
        'guardians.view',
        'guardians.manage',
        'attendance.view',
        'attendance.mark',
        'attendance.unlock',
        'attendance.scan',
        'staff_attendance.view',
        'staff_attendance.mark',
        'staff_attendance.unlock',
        'subjects.view',
        'subjects.manage',
        'exams.view',
        'exams.manage',
        'exams.record_marks',
        'exams.publish',
        'bank_accounts.view',
        'bank_accounts.manage',
        'fees.view',
        'fees.manage',
        'fees.invoice',
        'fees.collect',
        'certificates.view',
        'certificates.issue',
        'certificates.revoke',
        'certificate_templates.manage',
        'id_cards.view',
        'id_cards.issue',
        'id_cards.revoke',
        'id_card_templates.manage',
        'transport.view',
        'transport.manage',
        'communications.view',
        'communications.send',
        'communications.manage_templates',
        'reports.view',
        'hr.view',
        'hr.manage',
        'leave.approve',
        'inventory.view',
        'inventory.manage',
        'procurement.approve',
        'library.view',
        'library.manage',
        'hostel.view',
        'hostel.manage',
        'virtual_classes.view',
        'virtual_classes.manage',
        'lessons.view',
        'lessons.manage',
        'homework.view',
        'homework.manage',
        'online_exams.view',
        'online_exams.manage',
        'results.view',
        'results.manage',
        'timetable.view',
        'timetable.manage',
        'payroll.view',
        'payroll.manage',
        'payroll.disburse',
        'school.manage',
    ],

    /*
     * Permissions granted to the "Teacher" role at provisioning time.
     * Deliberately narrower than School Administrator — a teacher can
     * mark/view attendance, record exam marks, and look up students, but
     * can't manage users, roles, academic structure, exam setup, or
     * publish results. Virtual classes and the Learning Hub (lessons,
     * homework, online exams) get full "manage" here too: creating and
     * posting this content is squarely a teacher's own job, not an admin
     * setup task like the rest of this list.
     */
    'teacher_permissions' => [
        'students.view',
        'attendance.view',
        'attendance.mark',
        'attendance.scan',
        'subjects.view',
        'exams.view',
        'exams.record_marks',
        'virtual_classes.view',
        'virtual_classes.manage',
        'lessons.view',
        'lessons.manage',
        'homework.view',
        'homework.manage',
        'online_exams.view',
        'online_exams.manage',
        'results.view',
        'timetable.view',
    ],

    /*
     * Permissions granted to the "Accountant" role at provisioning time —
     * fees/payments are their domain; they don't need academic, roles,
     * or user-management access.
     */
    'accountant_permissions' => [
        'students.view',
        'bank_accounts.view',
        'bank_accounts.manage',
        'fees.view',
        'fees.manage',
        'fees.invoice',
        'fees.collect',
        'communications.view',
        'communications.send',
        'payroll.view',
        'payroll.disburse',
    ],

    /*
     * Permissions granted to the "Transport Manager" role — vehicles,
     * routes, and student-route assignment are their domain.
     */
    'transport_manager_permissions' => [
        'students.view',
        'transport.view',
        'transport.manage',
    ],

    /*
     * Permissions granted to the "Driver" role — can see their assigned
     * vehicle/route and push location updates, nothing else. Which
     * vehicle a driver may update is enforced in the controller (must be
     * the vehicle's driver_user_id), not by this permission alone.
     */
    'driver_permissions' => [
        'transport.view',
        'transport.track',
    ],

    /*
     * Permissions granted to the "Parent" role — view-only. A guardian
     * seeing their own child's transport assignment (rather than every
     * vehicle in the school) requires guardian-to-user account linkage
     * that doesn't exist yet (Guardian.user_id has no login flow built);
     * granted here per the "add permissions when the module ships"
     * convention, but the guardian-scoped endpoint is not implemented —
     * see FILE_PLAN.md.
     */
    'parent_permissions' => [
        // Deliberately empty. A parent sees their own children (bus, fees, attendance)
        // through the guardian record linked to their login (ParentController), not
        // through school-wide permissions such as transport.view, which would expose
        // every vehicle and driver in the school.
    ],

    /*
     * Permissions granted to the "HR" role — staff records and leave
     * approval are their domain. Note: any authenticated staff member
     * (any role) can apply for their own leave and see their own
     * requests regardless of this list — that's a floor capability
     * gated on "is a real user account", not a Spatie permission, so it
     * doesn't need threading through every other role's array here.
     */
    'hr_permissions' => [
        'hr.view',
        'hr.manage',
        'leave.approve',
        'payroll.view',
        'payroll.manage',
        'staff_attendance.view',
        'staff_attendance.mark',
    ],

    /*
     * Permissions granted to the "Receptionist" role — the front desk's
     * whole job here, for now, is the gate ID scanner: mark whoever just
     * walked in (student or staff) present, and see today's attendance.
     * Not given attendance.unlock or staff_attendance.mark/unlock — those
     * stay with whoever actually owns each register (a teacher/HR/admin),
     * a scan is a lighter-weight, always-available action by design.
     */
    'receptionist_permissions' => [
        'attendance.view',
        'attendance.scan',
        'staff_attendance.view',
        'students.view',
    ],

    /*
     * Permissions granted to the "Storekeeper" role — inventory and
     * procurement approval are their domain. Like "leave.apply", any
     * staff member can always *submit* a purchase request regardless of
     * this list — only approving one and managing the catalog requires
     * these permissions.
     */
    'storekeeper_permissions' => [
        'inventory.view',
        'inventory.manage',
        'procurement.approve',
    ],

    /*
     * Permissions granted to the "Librarian" role — the book catalog and
     * borrow/return workflow are their domain.
     */
    'librarian_permissions' => [
        'library.view',
        'library.manage',
    ],

    /*
     * Permissions granted to the "Warden" role — hostels, rooms, and
     * allocations are their domain. New role, not a previously-placeholder
     * one — see the note on default_school_roles above.
     */
    'warden_permissions' => [
        'hostel.view',
        'hostel.manage',
    ],

    /*
     * Standing changelog of default-role permissions added AFTER schools
     * already existed, so they can be rolled out to every existing tenant —
     * see App\Console\Commands\SyncTenantPermissions for how and why (in
     * short: `php artisan tenants:sync-permissions`, additive only, safe to
     * re-run). A brand-new school never needs this list — DefaultRoleProvisioner
     * already builds every role from the *_permissions arrays above in full.
     *
     * Add an entry here in the SAME change that adds the permission to one
     * of the *_permissions arrays above, then run the command.
     */
    'additive_grants' => [
        'students.delete' => ['School Administrator'],
        'attendance.unlock' => ['School Administrator'],
        'lessons.view' => ['School Administrator', 'Teacher'],
        'lessons.manage' => ['School Administrator', 'Teacher'],
        'homework.view' => ['School Administrator', 'Teacher'],
        'homework.manage' => ['School Administrator', 'Teacher'],
        'online_exams.view' => ['School Administrator', 'Teacher'],
        'online_exams.manage' => ['School Administrator', 'Teacher'],
        'attendance.scan' => ['School Administrator', 'Teacher', 'Receptionist'],
        'staff_attendance.view' => ['School Administrator', 'HR', 'Receptionist'],
        'staff_attendance.mark' => ['School Administrator', 'HR'],
        'staff_attendance.unlock' => ['School Administrator'],
        'students.view' => ['Receptionist'],
    ],
];
