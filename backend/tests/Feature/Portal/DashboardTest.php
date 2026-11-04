<?php

namespace Tests\Feature\Portal;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\RouteStop;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DefaultRoleProvisioner;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);

        $this->school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active', 'currency' => 'TZS']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
    }

    protected function user(string $name, string $role, ?School $school = null): User
    {
        $school ??= $this->school;
        setPermissionsTeamId($school->id);
        $user = User::create(['school_id' => $school->id, 'name' => $name, 'email' => str($school->slug.$name)->slug().'@test.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    protected function seedSchool(): array
    {
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $form1 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);
        $form2 = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 2']);

        $students = collect([[1, $form1], [2, $form1], [3, $form2], [4, $form2]])->map(fn ($x) => Student::create([
            'school_id' => $this->school->id, 'school_class_id' => $x[1]->id, 'academic_year_id' => $year->id,
            'admission_number' => "STU-$x[0]", 'first_name' => "Kid$x[0]", 'last_name' => 'Test', 'gender' => 'male',
            'date_of_birth' => '2015-01-01', 'admission_date' => now()->toDateString(), 'status' => 'active',
        ]));

        // Attendance today: 3 of the 4 students present → 75%.
        foreach (['present', 'present', 'present', 'absent'] as $i => $status) {
            $student = $students[$i];
            AttendanceRecord::create(['school_id' => $this->school->id, 'student_id' => $student->id, 'school_class_id' => $student->school_class_id, 'date' => now()->toDateString(), 'status' => $status]);
        }

        $invoice = Invoice::create([
            'school_id' => $this->school->id, 'student_id' => $students[0]->id, 'academic_year_id' => $year->id,
            'invoice_number' => 'INV-1', 'control_number' => 'CN-1', 'total_amount' => 500000, 'amount_paid' => 200000,
            'status' => 'partial', 'issued_date' => now()->toDateString(), 'due_date' => now()->subDays(3)->toDateString(),
        ]);
        Payment::create(['school_id' => $this->school->id, 'invoice_id' => $invoice->id, 'student_id' => $students[0]->id, 'receipt_number' => 'RCPT-1', 'amount' => 200000, 'type' => 'payment', 'method' => 'cash', 'paid_on' => now()->toDateString()]);

        return [$students, $invoice];
    }

    public function test_the_school_admin_dashboard_shows_real_numbers(): void
    {
        $this->seedSchool();
        $admin = $this->user('Admin', 'School Administrator');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Active students')->assertSee('>4<', false)
            ->assertSee('Attendance today')->assertSee('75%')
            ->assertSee('Fees collected this month')->assertSee('200,000')
            ->assertSee('Outstanding fees')->assertSee('300,000')->assertSee('1 overdue invoices')
            ->assertSee('Students by class')->assertSee('Form 1')
            ->assertSee('Recent payments')->assertSee('RCPT-1')
            ->assertDontSee('placeholder landing screen');
    }

    public function test_pending_leave_and_running_buses_appear_for_those_who_manage_them(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $teacher = $this->user('Teacher', 'Teacher');
        $type = LeaveType::create(['school_id' => $this->school->id, 'name' => 'Annual', 'days_per_year' => 21]);
        LeaveRequest::create(['school_id' => $this->school->id, 'user_id' => $teacher->id, 'leave_type_id' => $type->id, 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString(), 'days' => 2, 'status' => 'pending']);

        $driver = $this->user('Driver', 'Driver');
        $bus = Vehicle::create(['school_id' => $this->school->id, 'driver_user_id' => $driver->id, 'registration_number' => 'T 111 AAA'])->refresh();
        $route = TransportRoute::create(['school_id' => $this->school->id, 'vehicle_id' => $bus->id, 'name' => 'Route A'])->refresh();
        RouteStop::create(['transport_route_id' => $route->id, 'name' => 'Mikocheni', 'sequence' => 1, 'latitude' => -6.79, 'longitude' => 39.2]);
        app(TripService::class)->start($this->school, $driver, $bus, $route, 'morning');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Leave awaiting approval')->assertSee('Teacher')
            ->assertSee('Buses on the road')->assertSee('T 111 AAA')->assertSee('Route A');
    }

    public function test_an_accountant_sees_fees_but_not_leave_or_buses(): void
    {
        $this->seedSchool();
        $accountant = $this->user('Accountant', 'Accountant');

        $this->actingAs($accountant)->get(route('dashboard'))->assertOk()
            ->assertSee('Fees collected this month')->assertSee('Outstanding fees')
            ->assertDontSee('Leave awaiting approval')->assertDontSee('Buses on the road');
    }

    public function test_a_teacher_sees_attendance_but_no_money(): void
    {
        $this->seedSchool();
        $teacher = $this->user('Teacher', 'Teacher');

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()
            ->assertSee('Attendance today')
            ->assertDontSee('Fees collected this month')->assertDontSee('Outstanding fees')->assertDontSee('Recent payments');
    }

    public function test_an_empty_school_still_renders_and_other_schools_data_is_not_counted(): void
    {
        $other = School::create(['name' => 'Moonlight', 'slug' => 'moonlight', 'status' => 'active']);
        $year = AcademicYear::create(['school_id' => $other->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $other->id, 'name' => 'Other class']);
        Student::create(['school_id' => $other->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'X-1', 'first_name' => 'Far', 'last_name' => 'Away', 'gender' => 'male', 'date_of_birth' => '2015-01-01', 'admission_date' => now()->toDateString(), 'status' => 'active']);

        $admin = $this->user('Admin', 'School Administrator');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Active students')->assertSee('>0<', false)->assertSee('Not marked yet')
            ->assertDontSee('Other class')->assertDontSee('Far Away');
    }

    public function test_the_live_buses_page_and_feed_are_for_transport_managers_only(): void
    {
        $admin = $this->user('Admin', 'School Administrator');
        $teacher = $this->user('Teacher', 'Teacher');

        $this->actingAs($admin)->get(route('school-admin.transport-live'))->assertOk()->assertSee('Live buses');
        $this->actingAs($admin)->getJson(route('school-admin.transport-live.data'))->assertOk()->assertJsonPath('data', []);

        $this->actingAs($teacher)->get(route('school-admin.transport-live'))->assertForbidden();
        $this->actingAs($teacher)->getJson(route('school-admin.transport-live.data'))->assertForbidden();
    }
}
