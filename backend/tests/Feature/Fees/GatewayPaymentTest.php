<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GatewayPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    }

    protected function scenario(): array
    {
        $school = School::create(['name' => 'Sunrise', 'slug' => 'sunrise', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);

        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Form 1']);

        setPermissionsTeamId($school->id);
        $parentUser = User::create(['school_id' => $school->id, 'name' => 'Mother', 'email' => 'mother@test.local', 'password' => 'password', 'status' => 'active']);
        $parentUser->assignRole('Parent');
        $guardian = Guardian::create(['school_id' => $school->id, 'user_id' => $parentUser->id, 'name' => 'Mother', 'phone' => '0700000000']);

        $admin = User::create(['school_id' => $school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole('School Administrator');

        $student = Student::create([
            'school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'STU-1', 'first_name' => 'Neema', 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ])->refresh();
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        Sanctum::actingAs($admin);
        $structureId = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Form 1 Fees', 'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->json('data.id');
        $invoiceUuid = $this->postJson("/api/v1/fee-structures/{$structureId}/invoices", [])->json('data.0.id');

        return compact('school', 'student', 'parentUser', 'invoiceUuid');
    }

    public function test_guardian_can_pay_their_childs_invoice_and_it_settles_immediately_in_dev_mode(): void
    {
        ['student' => $student, 'parentUser' => $parentUser, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        Sanctum::actingAs($parentUser);
        $response = $this->postJson("/api/v1/my/children/{$student->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'Airtel',
        ])->assertOk();

        $response->assertJsonPath('data.status', 'completed');

        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('100000.00', (string) $invoice->amount_paid);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame('mobile_money', $invoice->payments()->first()->method);
    }

    public function test_guardian_can_pay_part_of_the_balance(): void
    {
        ['student' => $student, 'parentUser' => $parentUser, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        Sanctum::actingAs($parentUser);
        $this->postJson("/api/v1/my/children/{$student->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'Airtel', 'amount' => 40000,
        ])->assertOk();

        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('40000.00', (string) $invoice->amount_paid);
    }

    public function test_cannot_pay_more_than_the_outstanding_balance(): void
    {
        ['student' => $student, 'parentUser' => $parentUser, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        Sanctum::actingAs($parentUser);
        $this->postJson("/api/v1/my/children/{$student->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'Airtel', 'amount' => 999999,
        ])->assertUnprocessable();
    }

    public function test_cannot_pay_an_already_settled_invoice(): void
    {
        ['student' => $student, 'parentUser' => $parentUser, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        Sanctum::actingAs($parentUser);
        $this->postJson("/api/v1/my/children/{$student->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'Airtel',
        ])->assertOk();

        $this->postJson("/api/v1/my/children/{$student->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'Airtel',
        ])->assertUnprocessable();
    }

    public function test_a_guardian_cannot_pay_another_guardians_child(): void
    {
        ['invoiceUuid' => $invoiceUuid] = $this->scenario();

        $school = School::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        app(DefaultRoleProvisioner::class)->provision($school);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        setPermissionsTeamId($school->id);
        $strangerUser = User::create(['school_id' => $school->id, 'name' => 'Stranger', 'email' => 'stranger@test.local', 'password' => 'password', 'status' => 'active']);
        $strangerUser->assignRole('Parent');
        $strangerGuardian = Guardian::create(['school_id' => $school->id, 'user_id' => $strangerUser->id, 'name' => 'Stranger', 'phone' => '0722222222']);
        $strangerChild = Student::create([
            'school_id' => $school->id, 'academic_year_id' => $year->id, 'admission_number' => 'STU-X', 'first_name' => 'Other', 'last_name' => 'Child',
            'gender' => 'male', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ]);
        $strangerChild->guardians()->attach($strangerGuardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        Sanctum::actingAs($strangerUser);
        // Pretending to be their own child's uuid but pointing at someone else's invoice.
        $this->postJson("/api/v1/my/children/{$strangerChild->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'Airtel',
        ])->assertNotFound();
    }

    public function test_azampay_checkout_is_pending_until_the_callback_confirms_it(): void
    {
        config(['services.payment_gateway.driver' => 'azampay']);
        config(['services.payment_gateway.azampay.app_name' => 'ElimuGrid']);
        config(['services.payment_gateway.azampay.client_id' => 'id']);
        config(['services.payment_gateway.azampay.client_secret' => 'secret']);
        config(['services.payment_gateway.azampay.callback_token' => 'test-token']);

        Http::fake([
            'authenticator-sandbox.azampay.co.tz/*' => Http::response(['data' => ['accessToken' => 'fake-token']], 200),
            'sandbox.azampay.co.tz/azampay/mno/checkout' => Http::response(['success' => true, 'transactionId' => 'AZM-1'], 200),
        ]);

        ['student' => $student, 'parentUser' => $parentUser, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        Sanctum::actingAs($parentUser);
        $initiate = $this->postJson("/api/v1/my/children/{$student->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'Airtel',
        ])->assertOk();

        $initiate->assertJsonPath('data.status', 'pending');
        $transactionUuid = $initiate->json('data.id');

        // Invoice isn't paid yet — only the push was accepted, not approved.
        $this->assertSame('unpaid', Invoice::where('uuid', $invoiceUuid)->value('status'));

        // AzamPay calls our callback once the guardian approves it on their phone.
        $externalId = \App\Models\PaymentGatewayTransaction::where('uuid', $transactionUuid)->value('external_id');
        $this->postJson('/api/v1/payments/azampay/callback?token=test-token', [
            'externalId' => $externalId, 'transactionstatus' => 'success', 'transactionId' => 'AZM-1',
        ])->assertOk();

        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(1, $invoice->payments()->count());

        // A retried callback (gateways do this) must not double-charge.
        $this->postJson('/api/v1/payments/azampay/callback?token=test-token', [
            'externalId' => $externalId, 'transactionstatus' => 'success', 'transactionId' => 'AZM-1',
        ])->assertOk();
        $this->assertSame(1, $invoice->fresh()->payments()->count());
    }

    public function test_azampay_callback_without_the_correct_token_is_rejected(): void
    {
        config(['services.payment_gateway.azampay.callback_token' => 'test-token']);

        ['invoiceUuid' => $invoiceUuid] = $this->scenario();

        $this->postJson('/api/v1/payments/azampay/callback', [
            'externalId' => 'does-not-matter', 'transactionstatus' => 'success',
        ])->assertNotFound();

        $this->postJson('/api/v1/payments/azampay/callback?token=wrong', [
            'externalId' => 'does-not-matter', 'transactionstatus' => 'success',
        ])->assertNotFound();

        $this->assertSame('unpaid', Invoice::where('uuid', $invoiceUuid)->value('status'));
    }

    public function test_a_bank_provider_is_routed_to_the_bank_checkout_endpoint_not_mno(): void
    {
        config(['services.payment_gateway.driver' => 'azampay']);
        config(['services.payment_gateway.azampay.app_name' => 'ElimuGrid']);
        config(['services.payment_gateway.azampay.client_id' => 'id']);
        config(['services.payment_gateway.azampay.client_secret' => 'secret']);
        config(['services.payment_gateway.azampay.callback_token' => 'test-token']);

        Http::fake([
            'authenticator-sandbox.azampay.co.tz/*' => Http::response(['data' => ['accessToken' => 'fake-token']], 200),
            'sandbox.azampay.co.tz/azampay/bank/checkout' => Http::response(['success' => true, 'transactionId' => 'AZM-BANK-1'], 200),
            'sandbox.azampay.co.tz/azampay/mno/checkout' => Http::response(['success' => false, 'message' => 'wrong endpoint for this provider'], 200),
        ]);

        ['student' => $student, 'parentUser' => $parentUser, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        Sanctum::actingAs($parentUser);
        $this->postJson("/api/v1/my/children/{$student->uuid}/invoices/{$invoiceUuid}/pay", [
            'msisdn' => '0712345678', 'provider' => 'CRDB',
        ])->assertOk()->assertJsonPath('data.status', 'pending');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'azampay/bank/checkout'));
    }

    public function test_azampay_callback_for_an_unknown_transaction_is_ignored_not_crashed(): void
    {
        config(['services.payment_gateway.azampay.callback_token' => 'test-token']);

        $this->postJson('/api/v1/payments/azampay/callback?token=test-token', [
            'externalId' => (string) \Illuminate\Support\Str::uuid(), 'transactionstatus' => 'success',
        ])->assertOk();
    }
}
