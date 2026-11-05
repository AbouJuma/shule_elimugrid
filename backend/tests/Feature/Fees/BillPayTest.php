<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicYear;
use App\Models\BillPayPayment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AzamPay's Bill Pay product (a guardian pays by entering the control
 * number into any bank/network's own "pay bill" menu — see
 * App\Services\BillPayService). Unverified against a real AzamPay account
 * (there's no self-service sandbox for this product — see
 * config('services.bill_pay')), so these tests play the role of AzamPay
 * ourselves: build a request signed exactly the way their docs describe
 * (HS256 JWT + HMAC hash) with a test secret, and confirm our own
 * verification and FIFO logic behave correctly against it.
 */
class BillPayTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test-billpay-shared-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
        config(['services.bill_pay.shared_secret' => $this->secret]);
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
            'admission_number' => 'STU-1', 'control_number' => '8000000001', 'first_name' => 'Neema', 'last_name' => 'Test',
            'gender' => 'female', 'date_of_birth' => '2014-01-01', 'admission_date' => '2026-01-10',
        ])->refresh();
        $student->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

        Sanctum::actingAs($admin);
        $structureId = $this->postJson('/api/v1/fee-structures', [
            'name' => 'Form 1 Fees', 'academic_year_id' => $year->id, 'school_class_id' => $class->id,
            'items' => [['name' => 'Tuition', 'amount' => 100000]],
        ])->json('data.id');
        $invoiceUuid = $this->postJson("/api/v1/fee-structures/{$structureId}/invoices", [])->json('data.0.id');

        return compact('school', 'student', 'invoiceUuid');
    }

    protected function jwt(?string $secret = null): string
    {
        $secret ??= $this->secret;
        $header = $this->b64(['alg' => 'HS256', 'typ' => 'JWT']);
        $now = time();
        $payload = $this->b64(['userId' => 'azampay-test', 'iat' => $now, 'exp' => $now + 120]);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '+/', '-_'), '=');

        return "{$header}.{$payload}.{$signature}";
    }

    protected function b64(array $value): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($value)), '+/', '-_'), '=');
    }

    /** @param  array<string, mixed>  $data */
    protected function hashFor(array $data, ?string $secret = null): string
    {
        $secret ??= $this->secret;
        $minified = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', hash('sha256', (string) $minified), $secret);
    }

    protected function billPayPost(string $uri, array $data, ?string $jwt = null, ?string $hash = null)
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.($jwt ?? $this->jwt())])
            ->postJson($uri, ['Data' => $data, 'Hash' => $hash ?? $this->hashFor($data)]);
    }

    public function test_name_lookup_returns_the_students_total_outstanding_balance(): void
    {
        ['student' => $student] = $this->scenario();

        $response = $this->billPayPost('/api/v1/billpay/name-lookup', ['BillIdentifier' => $student->control_number])
            ->assertOk();

        $response->assertJsonPath('Status', 'Success');
        $response->assertJsonPath('Name', $student->fullName());
        $response->assertJsonPath('BillAmount', 100000);
    }

    public function test_name_lookup_for_an_unknown_control_number_fails_gracefully(): void
    {
        $this->scenario();

        $this->billPayPost('/api/v1/billpay/name-lookup', ['BillIdentifier' => '9999999999'])
            ->assertOk()
            ->assertJsonPath('Status', 'Failed');
    }

    public function test_a_request_with_the_wrong_signature_is_rejected(): void
    {
        ['student' => $student] = $this->scenario();

        $this->billPayPost('/api/v1/billpay/name-lookup', ['BillIdentifier' => $student->control_number], null, 'wrong-hash')
            ->assertNotFound();
    }

    public function test_a_request_without_the_shared_secret_configured_is_rejected(): void
    {
        config(['services.bill_pay.shared_secret' => null]);
        ['student' => $student] = $this->scenario();

        $this->billPayPost('/api/v1/billpay/name-lookup', ['BillIdentifier' => $student->control_number])
            ->assertNotFound();
    }

    public function test_a_partial_control_number_payment_reduces_the_balance(): void
    {
        ['student' => $student, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        $this->billPayPost('/api/v1/billpay/payment', [
            'BillIdentifier' => $student->control_number, 'Amount' => 40000, 'FspReferenceId' => 'FSP-1',
            'FspCode' => 'Airtel', 'BillType' => 'SchoolFees',
        ])->assertOk()->assertJsonPath('Status', 'Success');

        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('40000.00', (string) $invoice->amount_paid);
        $this->assertSame('mobile_money', $invoice->payments()->first()->method);
    }

    public function test_a_control_number_payment_is_split_fifo_across_two_open_invoices(): void
    {
        ['student' => $student, 'invoiceUuid' => $firstInvoiceUuid, 'school' => $school] = $this->scenario();

        $year = \App\Models\AcademicYear::where('school_id', $school->id)->first();
        $secondInvoice = Invoice::create([
            'school_id' => $school->id, 'student_id' => $student->id, 'academic_year_id' => $year->id,
            'invoice_number' => 'INV-SECOND', 'control_number' => $student->control_number,
            'total_amount' => 50000, 'amount_paid' => 0, 'status' => 'unpaid', 'issued_date' => now()->addDay()->toDateString(),
        ]);

        // Total owed across both invoices is 150,000. Paying 120,000 should
        // fully settle the first (issued earlier) and leave 20,000 owing on
        // the second.
        $this->billPayPost('/api/v1/billpay/payment', [
            'BillIdentifier' => $student->control_number, 'Amount' => 120000, 'FspReferenceId' => 'FSP-2', 'FspCode' => 'CRDB',
        ])->assertOk()->assertJsonPath('Status', 'Success');

        $first = Invoice::where('uuid', $firstInvoiceUuid)->first();
        $second = $secondInvoice->fresh();

        $this->assertSame('paid', $first->status);
        $this->assertSame('partial', $second->status);
        $this->assertSame('30000.00', $second->balance());
        $this->assertSame('bank_transfer', $second->payments()->first()->method);
    }

    public function test_a_retried_payment_with_the_same_fsp_reference_is_not_applied_twice(): void
    {
        ['student' => $student, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        $payload = ['BillIdentifier' => $student->control_number, 'Amount' => 40000, 'FspReferenceId' => 'FSP-DUP', 'FspCode' => 'Airtel'];

        $this->billPayPost('/api/v1/billpay/payment', $payload)->assertOk();
        $this->billPayPost('/api/v1/billpay/payment', $payload)->assertOk();

        $this->assertSame(1, BillPayPayment::count());
        $this->assertSame('40000.00', (string) Invoice::where('uuid', $invoiceUuid)->value('amount_paid'));
    }

    public function test_an_overpayment_beyond_everything_owed_is_kept_not_lost(): void
    {
        ['student' => $student, 'invoiceUuid' => $invoiceUuid] = $this->scenario();

        $this->billPayPost('/api/v1/billpay/payment', [
            'BillIdentifier' => $student->control_number, 'Amount' => 150000, 'FspReferenceId' => 'FSP-OVER', 'FspCode' => 'Tigo',
        ])->assertOk();

        $invoice = Invoice::where('uuid', $invoiceUuid)->first();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('150000.00', (string) $invoice->amount_paid);
        $this->assertSame(2, $invoice->payments()->count());
    }

    public function test_status_check_reports_a_completed_bill_pay_payment(): void
    {
        ['student' => $student] = $this->scenario();

        $this->billPayPost('/api/v1/billpay/payment', [
            'BillIdentifier' => $student->control_number, 'Amount' => 40000, 'FspReferenceId' => 'FSP-STATUS', 'FspCode' => 'Airtel',
        ])->assertOk();

        $merchantReferenceId = BillPayPayment::where('fsp_reference_id', 'FSP-STATUS')->value('merchant_reference_id');

        $this->billPayPost('/api/v1/billpay/status-check', ['MerchantReferenceId' => $merchantReferenceId])
            ->assertOk()
            ->assertJsonPath('Status', 'Success');
    }
}
