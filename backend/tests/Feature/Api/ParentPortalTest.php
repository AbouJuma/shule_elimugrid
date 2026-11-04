<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Invoice;
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
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Parent logins and what a parent can see: only their own children, never the whole school. */
class ParentPortalTest extends TestCase
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

    protected function user(string $name, string $role): User
    {
        setPermissionsTeamId($this->school->id);
        $user = User::create(['school_id' => $this->school->id, 'name' => $name, 'email' => str($name)->slug().'@test.local', 'password' => 'password', 'status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    protected function child(string $first, ?Guardian $guardian = null): Student
    {
        $year = AcademicYear::firstOrCreate(['school_id' => $this->school->id, 'name' => '2026'], ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::firstOrCreate(['school_id' => $this->school->id, 'name' => 'Form 1']);

        $student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'admission_number' => 'S-'.random_int(1000, 9999), 'first_name' => $first, 'last_name' => 'Kileo', 'gender' => 'female', 'date_of_birth' => '2015-01-01', 'admission_date' => '2026-01-15'])->refresh();

        if ($guardian) {
            $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);
        }

        return $student;
    }

    /** A parent with a login, one child on a bus route (stop 2 of 3). */
    protected function scenario(): array
    {
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678', 'email' => 'asha@test.local']);
        $parent = $this->user('Asha Kileo', 'Parent');
        $guardian->update(['user_id' => $parent->id]);

        $driver = $this->user('Driver', 'Driver');
        $bus = Vehicle::create(['school_id' => $this->school->id, 'driver_user_id' => $driver->id, 'registration_number' => 'T 452 DKL'])->refresh();
        $route = TransportRoute::create(['school_id' => $this->school->id, 'vehicle_id' => $bus->id, 'name' => 'Kawe route'])->refresh();
        $stops = collect([['Kawe', -6.7300, 39.2200], ['Mwenge', -6.7440, 39.2280], ['Mikocheni', -6.7610, 39.2420]])
            ->map(fn ($s, $i) => RouteStop::create(['transport_route_id' => $route->id, 'name' => $s[0], 'sequence' => $i + 1, 'latitude' => $s[1], 'longitude' => $s[2]]));

        $kid = $this->child('Neema', $guardian);
        $kid->update(['transport_route_id' => $route->id, 'route_stop_id' => $stops[1]->id]);

        return compact('guardian', 'parent', 'driver', 'bus', 'route', 'stops', 'kid');
    }

    /** Each real request gets a fresh guard; in one test process the last request's guard would linger. */
    protected function login(): static
    {
        app('auth')->forgetGuards();
        app('auth')->shouldUse('web');

        return $this;
    }

    // ---- Creating a parent login -----------------------------------------------------------------------------------

    public function test_a_guardian_can_be_given_a_login_and_it_only_carries_the_parent_role(): void
    {
        Queue::fake();
        $admin = $this->user('Admin', 'School Administrator');
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678']);
        Sanctum::actingAs($admin);

        // No email on file → one is required.
        $this->postJson("/api/v1/guardians/{$guardian->id}/account")->assertUnprocessable()->assertJsonValidationErrors('email');

        $r = $this->postJson("/api/v1/guardians/{$guardian->id}/account", ['email' => 'asha@parent.test'])->assertCreated();
        $password = $r->json('temporary_password');
        $this->assertNotEmpty($password);
        $r->assertJsonPath('data.has_login', true)->assertJsonPath('data.login_email', 'asha@parent.test');

        $user = User::where('email', 'asha@parent.test')->firstOrFail();
        $this->assertSame($user->id, $guardian->fresh()->user_id);
        setPermissionsTeamId($this->school->id);
        $this->assertTrue($user->hasRole('Parent'));
        $this->assertCount(0, $user->getAllPermissions(), 'a parent holds no school-wide permission');

        // The credentials go out by SMS.
        $sms = \App\Models\SmsLog::firstOrFail();
        $this->assertSame('account', $sms->category);
        $this->assertStringContainsString($password, $sms->message);

        // Second time: already has a login.
        $this->postJson("/api/v1/guardians/{$guardian->id}/account")->assertUnprocessable();

        // The parent can sign in with it.
        $this->login()->postJson('/api/v1/auth/login', ['email' => 'asha@parent.test', 'password' => $password, 'device_name' => 't'])->assertOk();
        Sanctum::actingAs($admin);

        // Reset gives a new password and the old one stops working.
        $new = $this->postJson("/api/v1/guardians/{$guardian->id}/account/reset")->assertOk()->json('temporary_password');
        $this->assertNotSame($password, $new);
        $this->assertSame(0, $user->fresh()->tokens()->count(), 'a reset signs them out everywhere');
        $this->login()->postJson('/api/v1/auth/login', ['email' => 'asha@parent.test', 'password' => $password, 'device_name' => 't'])->assertUnprocessable();
    }

    public function test_a_guardians_first_password_is_their_own_phone_number(): void
    {
        Queue::fake();
        $admin = $this->user('Admin', 'School Administrator');
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678']);
        Sanctum::actingAs($admin);

        $r = $this->postJson("/api/v1/guardians/{$guardian->id}/account", ['email' => 'asha.phone@parent.test'])->assertCreated();

        $this->assertSame('0712345678', $r->json('temporary_password'));

        // And it genuinely signs in with it — not just a displayed value.
        $this->login()->postJson('/api/v1/auth/login', [
            'email' => 'asha.phone@parent.test', 'password' => '0712345678', 'device_name' => 't',
        ])->assertOk();
    }

    public function test_only_people_who_manage_guardians_can_create_logins_and_emails_cannot_be_reused(): void
    {
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Asha', 'phone' => '0700', 'email' => 'taken@test.local']);
        $this->user('Someone', 'Teacher')->update(['email' => 'taken@test.local']);

        Sanctum::actingAs($this->user('Teacher Two', 'Teacher'));
        $this->postJson("/api/v1/guardians/{$guardian->id}/account")->assertForbidden();

        Sanctum::actingAs($this->user('Admin', 'School Administrator'));
        $this->postJson("/api/v1/guardians/{$guardian->id}/account")->assertUnprocessable();
    }

    // ---- What a parent sees -------------------------------------------------------------------------------------------------

    public function test_a_parent_sees_only_their_own_children(): void
    {
        $d = $this->scenario();
        $stranger = Guardian::create(['school_id' => $this->school->id, 'name' => 'Other Mum', 'phone' => '0799']);
        $otherKid = $this->child('Stranger', $stranger);
        $sibling = $this->child('Baraka', $d['guardian']);

        Sanctum::actingAs($d['parent']);

        $this->getJson('/api/v1/my/children')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Baraka Kileo')->assertJsonPath('data.1.transport.stop', 'Mwenge');

        $this->getJson("/api/v1/my/children/{$d['kid']->uuid}/fees")->assertOk();
        $this->getJson("/api/v1/my/children/{$otherKid->uuid}/fees")->assertNotFound();
        $this->getJson("/api/v1/my/children/{$otherKid->uuid}/bus")->assertNotFound();
        $this->getJson("/api/v1/my/children/{$otherKid->uuid}/attendance")->assertNotFound();
        $this->assertNotNull($sibling);

        // A staff member with no guardian record has no children.
        Sanctum::actingAs($this->user('Teacher', 'Teacher'));
        $this->getJson('/api/v1/my/children')->assertForbidden();
    }

    public function test_a_parent_cannot_use_the_school_wide_transport_endpoints(): void
    {
        $d = $this->scenario();
        Sanctum::actingAs($d['parent']);

        $this->getJson('/api/v1/vehicles')->assertForbidden();
        $this->getJson('/api/v1/transport/trips/active')->assertForbidden();
        $this->getJson('/api/v1/transport-routes')->assertForbidden();
    }

    public function test_the_bus_screen_covers_not_assigned_not_running_and_running(): void
    {
        $d = $this->scenario();
        $unassigned = $this->child('Juma', $d['guardian']);
        Sanctum::actingAs($d['parent']);

        $this->getJson("/api/v1/my/children/{$unassigned->uuid}/bus")->assertOk()->assertJsonPath('data.assigned', false);

        $this->getJson("/api/v1/my/children/{$d['kid']->uuid}/bus")->assertOk()
            ->assertJsonPath('data.assigned', true)->assertJsonPath('data.running', false)
            ->assertJsonPath('data.stop.name', 'Mwenge')->assertJsonPath('data.route', 'Kawe route');

        $trip = app(TripService::class)->start($this->school, $d['driver'], $d['bus'], $d['route'], 'morning');
        app(TripService::class)->recordLocation($trip, -6.7300, 39.2200, 30); // at stop 1 → auto-arrives

        $r = $this->getJson("/api/v1/my/children/{$d['kid']->uuid}/bus")->assertOk();
        $r->assertJsonPath('data.running', true)->assertJsonPath('data.trip.progress.visited', 1)->assertJsonPath('data.mine.state', 'coming')->assertJsonPath('data.mine.stops_away', 0);

        // The child's stop is next and about 2.3 km on (1.8 km straight × 1.3) at 30 km/h ≈ 4 min.
        $mine = $r->json('data.mine');
        $this->assertEqualsWithDelta(2330, $mine['distance_m'], 100);
        $this->assertGreaterThanOrEqual(3, $mine['eta_minutes']);
        $this->assertLessThanOrEqual(6, $mine['eta_minutes']);

        // Privacy: no student counts and no driver phone leak to a parent.
        $this->assertArrayNotHasKey('students', $r->json('data.trip.stops.0'));
        $this->assertArrayNotHasKey('phone', $r->json('data.trip.vehicle.driver') ?? []);
        $this->assertNull($r->json('data.trip.next'));

        // Once the bus has been at the child's stop the parent is told it has passed.
        app(TripService::class)->recordLocation($trip, -6.7440, 39.2280, 30);
        $this->getJson("/api/v1/my/children/{$d['kid']->uuid}/bus")->assertOk()->assertJsonPath('data.mine.state', 'passed');
    }

    public function test_afternoon_trips_reach_the_stops_in_reverse_so_the_eta_counts_stops_the_other_way(): void
    {
        $d = $this->scenario();
        Sanctum::actingAs($d['parent']);

        $trip = app(TripService::class)->start($this->school, $d['driver'], $d['bus'], $d['route'], 'afternoon');
        app(TripService::class)->recordLocation($trip, -6.7610, 39.2420, 30); // at the last stop → first of the afternoon run

        // Afternoon order: Mikocheni (done), Mwenge (next = the child's), Kawe.
        $this->getJson("/api/v1/my/children/{$d['kid']->uuid}/bus")->assertOk()->assertJsonPath('data.mine.stops_away', 0);
    }

    public function test_fees_and_attendance_summaries_are_for_the_child(): void
    {
        $d = $this->scenario();
        $year = AcademicYear::first();
        Invoice::create(['school_id' => $this->school->id, 'student_id' => $d['kid']->id, 'academic_year_id' => $year->id, 'invoice_number' => 'INV-1', 'control_number' => 'CN-1', 'total_amount' => 500000, 'amount_paid' => 200000, 'status' => 'partial', 'issued_date' => now()->toDateString()]);

        foreach (['present', 'present', 'late', 'absent'] as $i => $status) {
            AttendanceRecord::create(['school_id' => $this->school->id, 'student_id' => $d['kid']->id, 'school_class_id' => $d['kid']->school_class_id, 'date' => now()->subDays($i)->toDateString(), 'status' => $status]);
        }

        Sanctum::actingAs($d['parent']);

        $this->getJson("/api/v1/my/children/{$d['kid']->uuid}/fees")->assertOk()
            ->assertJsonPath('data.balance', '300000')->assertJsonPath('data.paid', '200000')->assertJsonCount(1, 'data.invoices')->assertJsonPath('data.currency', 'TZS');

        $this->getJson("/api/v1/my/children/{$d['kid']->uuid}/attendance")->assertOk()
            ->assertJsonPath('data.days', 4)->assertJsonPath('data.absent', 1)->assertJsonPath('data.rate', 75);
    }

    // ---- Changing your own password -----------------------------------------------------------------------------------------------

    public function test_a_user_can_change_their_password_and_other_devices_are_signed_out(): void
    {
        $user = $this->user('Asha', 'Parent');
        $other = $user->createToken('phone-2');
        $token = $user->createToken('phone-1');

        $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'NewPass1234', 'password_confirmation' => 'NewPass1234'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/password', ['current_password' => 'password', 'password' => 'NewPass1234', 'password_confirmation' => 'NewPass1234'])
            ->assertOk();

        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken($other->plainTextToken), 'the other device was signed out');
        $this->assertNotNull(\Laravel\Sanctum\PersonalAccessToken::findToken($token->plainTextToken), 'this device stays signed in');
        $this->login()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'NewPass1234', 'device_name' => 't'])->assertOk();
    }
}
