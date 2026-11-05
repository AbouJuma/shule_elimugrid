@php
    $active = $active ?? null;
    $user = auth()->user();

    // Which group (if any) the current page belongs to — used to
    // auto-expand that group so navigating never hides your own location.
    $groups = [
        'academics' => ['academics', 'terms', 'students', 'guardians', 'attendance', 'subjects', 'timetable', 'exams', 'results'],
        'finance' => ['fee-structures', 'invoices', 'bank-accounts'],
        'documents' => ['certificates', 'id-cards'],
        'transport' => ['vehicles', 'transport-routes', 'transport-live'],
        'hr' => ['staff', 'leave', 'payroll', 'staff-attendance'],
        'inventory' => ['inventory', 'procurement'],
        'hostel' => ['hostel', 'hostel-rooms', 'hostel-allocations'],
        'learning-hub' => ['lessons', 'homework', 'online-exams'],
    ];
    $activeGroup = collect($groups)->search(fn ($pages) => in_array($active, $pages));
@endphp

<a class="nav-link {{ $active === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
    <i class="bi bi-speedometer2 me-2"></i> {{ __('Dashboard') }}
</a>

@can('roles.view')
    <a class="nav-link {{ $active === 'roles' ? 'active' : '' }}" href="{{ route('school-admin.roles.index') }}">
        <i class="bi bi-shield-lock me-2"></i> {{ __('Roles') }}
    </a>
@endcan

@if ($user->canAny(['academic_years.view', 'students.view', 'guardians.manage', 'attendance.view', 'subjects.view', 'exams.view', 'results.view', 'timetable.view']))
    <a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupAcademics" role="button"
       aria-expanded="{{ $activeGroup === 'academics' ? 'true' : 'false' }}">
        <span><i class="bi bi-mortarboard me-2"></i> {{ __('Academics') }}</span>
        <i class="bi bi-chevron-down"></i>
    </a>
    <div class="collapse nav-submenu {{ $activeGroup === 'academics' ? 'show' : '' }}" id="navGroupAcademics">
        @can('academic_years.view')
            <a class="nav-link {{ $active === 'academics' ? 'active' : '' }}" href="{{ route('school-admin.academics.index') }}">{{ __('Academic Setup') }}</a>
            <a class="nav-link {{ $active === 'terms' ? 'active' : '' }}" href="{{ route('school-admin.terms.index') }}">{{ __('Terms') }}</a>
        @endcan
        @can('students.view')
            <a class="nav-link {{ $active === 'students' ? 'active' : '' }}" href="{{ route('school-admin.students.index') }}">{{ __('Students') }}</a>
        @endcan
        @can('guardians.manage')
            <a class="nav-link {{ $active === 'guardians' ? 'active' : '' }}" href="{{ route('school-admin.guardians.index') }}">{{ __('Guardians') }}</a>
        @endcan
        @can('attendance.view')
            <a class="nav-link {{ $active === 'attendance' ? 'active' : '' }}" href="{{ route('school-admin.attendance.index') }}">{{ __('Attendance') }}</a>
        @endcan
        @can('subjects.view')
            <a class="nav-link {{ $active === 'subjects' ? 'active' : '' }}" href="{{ route('school-admin.subjects.index') }}">{{ __('Subjects') }}</a>
        @endcan
        @can('timetable.view')
            <a class="nav-link {{ $active === 'timetable' ? 'active' : '' }}" href="{{ route('school-admin.timetable.index') }}">{{ __('Timetable') }}</a>
        @endcan
        @can('exams.view')
            <a class="nav-link {{ $active === 'exams' ? 'active' : '' }}" href="{{ route('school-admin.exams.index') }}">{{ __('Exams') }}</a>
        @endcan
        @can('results.view')
            <a class="nav-link {{ $active === 'results' ? 'active' : '' }}" href="{{ route('school-admin.results.index') }}">{{ __('Results & Report Cards') }}</a>
        @endcan
    </div>
@endif

@if ($user->canAny(['fees.view', 'bank_accounts.view']))
    <a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupFinance" role="button"
       aria-expanded="{{ $activeGroup === 'finance' ? 'true' : 'false' }}">
        <span><i class="bi bi-cash-coin me-2"></i> {{ __('Finance') }}</span>
        <i class="bi bi-chevron-down"></i>
    </a>
    <div class="collapse nav-submenu {{ $activeGroup === 'finance' ? 'show' : '' }}" id="navGroupFinance">
        @can('fees.view')
            <a class="nav-link {{ $active === 'fee-structures' ? 'active' : '' }}" href="{{ route('school-admin.fee-structures.index') }}">{{ __('Fee Structures') }}</a>
            <a class="nav-link {{ $active === 'invoices' ? 'active' : '' }}" href="{{ route('school-admin.invoices.index') }}">{{ __('Invoices') }}</a>
        @endcan
        @can('bank_accounts.view')
            <a class="nav-link {{ $active === 'bank-accounts' ? 'active' : '' }}" href="{{ route('school-admin.bank-accounts.index') }}">{{ __('Bank Accounts') }}</a>
        @endcan
    </div>
@endif

@if ($user->canAny(['certificates.view', 'id_cards.view']))
    <a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupDocuments" role="button"
       aria-expanded="{{ $activeGroup === 'documents' ? 'true' : 'false' }}">
        <span><i class="bi bi-award me-2"></i> {{ __('Documents') }}</span>
        <i class="bi bi-chevron-down"></i>
    </a>
    <div class="collapse nav-submenu {{ $activeGroup === 'documents' ? 'show' : '' }}" id="navGroupDocuments">
        @can('certificates.view')
            <a class="nav-link {{ $active === 'certificates' ? 'active' : '' }}" href="{{ route('school-admin.certificates.index') }}">{{ __('Certificates') }}</a>
        @endcan
        @can('id_cards.view')
            <a class="nav-link {{ $active === 'id-cards' ? 'active' : '' }}" href="{{ route('school-admin.id-cards.index') }}">{{ __('ID Cards') }}</a>
        @endcan
    </div>
@endif

@can('transport.view')
    <a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupTransport" role="button"
       aria-expanded="{{ $activeGroup === 'transport' ? 'true' : 'false' }}">
        <span><i class="bi bi-truck-front me-2"></i> {{ __('Transport') }}</span>
        <i class="bi bi-chevron-down"></i>
    </a>
    <div class="collapse nav-submenu {{ $activeGroup === 'transport' ? 'show' : '' }}" id="navGroupTransport">
        @can('transport.manage')
            <a class="nav-link {{ $active === 'transport-live' ? 'active' : '' }}" href="{{ route('school-admin.transport-live') }}"><i class="bi bi-broadcast me-1"></i>{{ __('Live buses') }}</a>
        @endcan
        <a class="nav-link {{ $active === 'vehicles' ? 'active' : '' }}" href="{{ route('school-admin.vehicles.index') }}">{{ __('Vehicles') }}</a>
        <a class="nav-link {{ $active === 'transport-routes' ? 'active' : '' }}" href="{{ route('school-admin.transport-routes.index') }}">{{ __('Transport Routes') }}</a>
    </div>
@endcan

@can('communications.view')
    <a class="nav-link {{ $active === 'communications' ? 'active' : '' }}" href="{{ route('school-admin.communications.index') }}">
        <i class="bi bi-chat-dots me-2"></i> {{ __('Communications') }}
    </a>
@endcan

@can('reports.view')
    <a class="nav-link {{ $active === 'reports' ? 'active' : '' }}" href="{{ route('school-admin.reports.index') }}">
        <i class="bi bi-bar-chart me-2"></i> {{ __('Reports') }}
    </a>
@endcan

{{-- HR group always shows — Leave (self-service) has no permission gate, every staff member can see their own requests --}}
<a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupHr" role="button"
   aria-expanded="{{ $activeGroup === 'hr' ? 'true' : 'false' }}">
    <span><i class="bi bi-person-badge me-2"></i> {{ __('HR & Payroll') }}</span>
    <i class="bi bi-chevron-down"></i>
</a>
<div class="collapse nav-submenu {{ $activeGroup === 'hr' ? 'show' : '' }}" id="navGroupHr">
    @can('hr.view')
        <a class="nav-link {{ $active === 'staff' ? 'active' : '' }}" href="{{ route('school-admin.staff.index') }}">{{ __('Staff') }}</a>
    @endcan
    @can('staff_attendance.view')
        <a class="nav-link {{ $active === 'staff-attendance' ? 'active' : '' }}" href="{{ route('school-admin.staff-attendance.index') }}">{{ __('Staff Attendance') }}</a>
    @endcan
    @can('payroll.view')
        <a class="nav-link {{ $active === 'payroll' ? 'active' : '' }}" href="{{ route('school-admin.payroll.index') }}">{{ __('Payroll') }}</a>
    @endcan
    <a class="nav-link {{ $active === 'leave' ? 'active' : '' }}" href="{{ route('school-admin.leave.index') }}">{{ __('Leave') }}</a>
</div>

{{-- Inventory group always shows — Procurement (submitting a request) has no permission gate either --}}
<a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupInventory" role="button"
   aria-expanded="{{ $activeGroup === 'inventory' ? 'true' : 'false' }}">
    <span><i class="bi bi-box-seam me-2"></i> {{ __('Inventory') }}</span>
    <i class="bi bi-chevron-down"></i>
</a>
<div class="collapse nav-submenu {{ $activeGroup === 'inventory' ? 'show' : '' }}" id="navGroupInventory">
    @can('inventory.view')
        <a class="nav-link {{ $active === 'inventory' ? 'active' : '' }}" href="{{ route('school-admin.inventory.index') }}">{{ __('Items') }}</a>
    @endcan
    <a class="nav-link {{ $active === 'procurement' ? 'active' : '' }}" href="{{ route('school-admin.procurement.index') }}">{{ __('Procurement') }}</a>
</div>

@can('library.view')
    <a class="nav-link {{ $active === 'library' ? 'active' : '' }}" href="{{ route('school-admin.library.index') }}">
        <i class="bi bi-journal-richtext me-2"></i> {{ __('Library') }}
    </a>
@endcan

@can('virtual_classes.view')
    <a class="nav-link {{ $active === 'virtual-classroom' ? 'active' : '' }}" href="{{ route('school-admin.virtual-classroom.index') }}">
        <i class="bi bi-camera-video me-2"></i> {{ __('Virtual Classroom') }}
    </a>
@endcan

@can('attendance.scan')
    <a class="nav-link {{ $active === 'attendance-scan' ? 'active' : '' }}" href="{{ route('school-admin.attendance-scan.index') }}">
        <i class="bi bi-upc-scan me-2"></i> {{ __('Scan Attendance') }}
    </a>
@endcan

@if ($user->canAny(['lessons.view', 'homework.view', 'online_exams.view']))
    <a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupLearningHub" role="button"
       aria-expanded="{{ $activeGroup === 'learning-hub' ? 'true' : 'false' }}">
        <span><i class="bi bi-play-btn me-2"></i> {{ __('Learning Hub') }}</span>
        <i class="bi bi-chevron-down"></i>
    </a>
    <div class="collapse nav-submenu {{ $activeGroup === 'learning-hub' ? 'show' : '' }}" id="navGroupLearningHub">
        @can('lessons.view')
            <a class="nav-link {{ $active === 'lessons' ? 'active' : '' }}" href="{{ route('school-admin.lessons.index') }}">{{ __('Lessons') }}</a>
        @endcan
        @can('homework.view')
            <a class="nav-link {{ $active === 'homework' ? 'active' : '' }}" href="{{ route('school-admin.homework.index') }}">{{ __('Homework') }}</a>
        @endcan
        @can('online_exams.view')
            <a class="nav-link {{ $active === 'online-exams' ? 'active' : '' }}" href="{{ route('school-admin.online-exams.index') }}">{{ __('Online Exams') }}</a>
        @endcan
    </div>
@endif

@can('hostel.view')
    <a class="nav-link nav-group-toggle" data-bs-toggle="collapse" href="#navGroupHostel" role="button"
       aria-expanded="{{ $activeGroup === 'hostel' ? 'true' : 'false' }}">
        <span><i class="bi bi-building me-2"></i> {{ __('Hostel') }}</span>
        <i class="bi bi-chevron-down"></i>
    </a>
    <div class="collapse nav-submenu {{ $activeGroup === 'hostel' ? 'show' : '' }}" id="navGroupHostel">
        <a class="nav-link {{ $active === 'hostel' ? 'active' : '' }}" href="{{ route('school-admin.hostel.index') }}">{{ __('Hostels') }}</a>
        <a class="nav-link {{ $active === 'hostel-rooms' ? 'active' : '' }}" href="{{ route('school-admin.hostel.rooms') }}">{{ __('Rooms') }}</a>
        <a class="nav-link {{ $active === 'hostel-allocations' ? 'active' : '' }}" href="{{ route('school-admin.hostel.allocations') }}">{{ __('Allocations') }}</a>
    </div>
@endcan

@can('school.manage')
    <a class="nav-link {{ $active === 'school-settings' ? 'active' : '' }}" href="{{ route('school-admin.settings.school') }}">
        <i class="bi bi-gear me-2"></i> {{ __('School Profile') }}
    </a>
@endcan
