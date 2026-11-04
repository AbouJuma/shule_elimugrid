<?php

namespace Tests\Feature\Communications;

use App\Models\AcademicYear;
use App\Models\BankAccount;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SmsLog;
use App\Models\Term;
use App\Models\User;
use App\Services\DefaultRoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The message a parent gets when their child is admitted: what to pay, the control number, the bank — in their language. */
class FeeMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected SchoolClass $class;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
        Queue::fake();

        $this->school = School::create(['name' => 'Sunrise Academy', 'slug' => 'sunrise', 'status' => 'active', 'currency' => 'TZS']);
        app(DefaultRoleProvisioner::class)->provision($this->school);
        $this->year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Form 1']);

        setPermissionsTeamId($this->school->id);
        $this->admin = User::create(['school_id' => $this->school->id, 'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'status' => 'active']);
        $this->admin->assignRole('School Administrator');
        Sanctum::actingAs($this->admin);
    }

    /** Term 1 fees: tuition 800,000 + uniform 50,000 = 850,000, and a CRDB account for fees. */
    protected function feeSetup(): FeeStructure
    {
        $term = Term::create(['school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'name' => 'Term 1', 'start_date' => '2026-01-05', 'end_date' => '2026-04-01', 'is_current' => true]);
        $structure = FeeStructure::create(['school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id, 'term_id' => $term->id, 'name' => 'Form 1 — Term 1']);
        $structure->items()->create(['name' => 'Tuition', 'amount' => 800000]);
        $structure->items()->create(['name' => 'Uniform', 'amount' => 50000]);
        BankAccount::create(['school_id' => $this->school->id, 'bank_name' => 'CRDB Bank', 'account_name' => 'Sunrise Academy', 'account_number' => '0150123456', 'currency' => 'TZS', 'purpose' => 'School Fees', 'is_active' => true]);

        return $structure;
    }

    protected function admit(array $guardian = [], array $extra = [])
    {
        return $this->postJson('/api/v1/students', [
            'student' => [
                'first_name' => 'Neema', 'last_name' => 'Kileo', 'gender' => 'female', 'date_of_birth' => '2014-03-02', 'admission_date' => '2026-01-10',
                'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id,
            ],
            'guardians' => [[...['name' => 'Asha Kileo', 'phone' => '0712345678', 'relationship' => 'mother', 'is_primary' => true], ...$guardian]],
            ...$extra,
        ]);
    }

    public function test_admitting_a_student_texts_the_parent_the_amount_control_number_and_bank_in_swahili(): void
    {
        $this->feeSetup();

        $r = $this->admit(['locale' => 'sw'])->assertCreated();

        $invoice = Invoice::firstOrFail();
        $r->assertJsonPath('notification.status', 'sent')->assertJsonPath('notification.control_number', $invoice->control_number);

        $this->assertSame('850000.00', (string) $invoice->total_amount);
        $this->assertNotNull($invoice->control_number);

        $sms = SmsLog::firstOrFail();
        $this->assertSame('fees', $sms->category);
        $this->assertSame('255712345678', $sms->recipient_phone);
        $this->assertStringContainsString('Ndugu Asha Kileo', $sms->message);
        $this->assertStringContainsString('Neema Kileo amesajiliwa Sunrise Academy', $sms->message);
        $this->assertStringContainsString('TZS 850,000', $sms->message);
        $this->assertStringContainsString('Namba ya Malipo '.$invoice->control_number, $sms->message);
        $this->assertStringContainsString('Benki: CRDB Bank, Jina la akaunti: Sunrise Academy, Namba ya akaunti: 0150123456.', $sms->message);
        $this->assertStringNotContainsString('{', $sms->message);
        $this->assertStringNotContainsString('  ', $sms->message);
    }

    public function test_the_language_is_the_parents_then_the_schools_then_english(): void
    {
        $this->feeSetup();

        // No language anywhere → the app's (English).
        $this->admit()->assertCreated();
        $this->assertStringContainsString('Pay using Control Number', SmsLog::latest('id')->first()->message);

        // The school's message language → Swahili.
        $this->school->update(['settings' => ['message_locale' => 'sw']]);
        $this->admit(['phone' => '0755000111'])->assertCreated();
        $this->assertStringContainsString('Lipa kwa Namba ya Malipo', SmsLog::latest('id')->first()->message);

        // The parent's own choice wins over the school's.
        $this->admit(['phone' => '0755000222', 'locale' => 'en'])->assertCreated();
        $this->assertStringContainsString('Pay using Control Number', SmsLog::latest('id')->first()->message);
    }

    public function test_a_due_date_is_added_only_when_there_is_one(): void
    {
        $structure = $this->feeSetup();
        $this->admit()->assertCreated();
        $this->assertStringNotContainsString('Pay by', SmsLog::firstOrFail()->message);

        // A class invoice with a due date, resent as the invoice notice.
        $other = \App\Models\Student::firstOrFail();
        Invoice::query()->delete();
        $this->postJson("/api/v1/fee-structures/{$structure->id}/invoices", ['due_date' => '2026-02-15', 'notify' => true])->assertCreated()->assertJsonPath('meta.texted', 1);

        $this->assertStringContainsString('Pay by 15 February 2026.', SmsLog::latest('id')->first()->message);
        $this->assertNotNull($other);
    }

    public function test_nothing_is_sent_and_no_invoice_made_when_the_school_switches_it_off(): void
    {
        $this->feeSetup();

        $this->admit([], ['notify_guardian' => false])->assertCreated()->assertJsonPath('notification.status', 'off');

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, SmsLog::count());
    }

    public function test_admission_still_succeeds_and_says_why_when_it_cannot_send(): void
    {
        // No fee structure for the class.
        $this->admit()->assertCreated()->assertJsonPath('notification.status', 'skipped')->assertJsonPath('notification.reason', 'no_fee_structure');

        // Parent without a phone: the API requires one for a new guardian, so use an existing guardian record with none.
        $this->feeSetup();
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'No Phone', 'phone' => '']);
        $this->postJson('/api/v1/students', [
            'student' => ['first_name' => 'Juma', 'last_name' => 'Ali', 'gender' => 'male', 'date_of_birth' => '2014-03-02', 'admission_date' => '2026-01-10', 'academic_year_id' => $this->year->id, 'school_class_id' => $this->class->id],
            'guardians' => [['guardian_id' => $guardian->id, 'relationship' => 'father', 'is_primary' => true]],
        ])->assertCreated()->assertJsonPath('notification.reason', 'no_guardian_phone');

        $this->assertSame(0, SmsLog::count());
        $this->assertSame(0, Invoice::count());
    }

    public function test_the_wording_is_the_schools_to_edit_and_a_message_must_keep_its_control_number(): void
    {
        $this->feeSetup();
        $this->admit(['locale' => 'sw'])->assertCreated();
        Invoice::query()->delete();

        $list = $this->getJson('/api/v1/message-templates')->assertOk();
        $row = collect($list->json('data'))->first(fn ($t) => $t['event'] === 'admission_fees' && $t['locale'] === 'sw');
        $this->assertTrue($row['is_system']);
        $this->assertContains('control_number', collect($row['tokens'])->pluck('token')->all());
        $this->assertStringContainsString('Ndugu Asha Kileo', $row['preview']);

        // Without {control_number} the message would be useless → refused.
        $this->putJson("/api/v1/message-templates/{$row['id']}", ['body' => 'Karibu {school_name}.'])->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->putJson("/api/v1/message-templates/{$row['id']}", ['body' => 'Karibu {student_name}! Lipa {currency} {amount} kwa {control_number}. {bank_details}'])->assertOk();

        $this->admit(['locale' => 'sw', 'phone' => '0755999888'])->assertCreated();
        $text = SmsLog::latest('id')->first()->message;
        $this->assertMatchesRegularExpression('/^Karibu Neema Kileo! Lipa TZS 850,000 kwa 8\d{9}\./', $text);

        // Back to the default wording.
        $this->postJson("/api/v1/message-templates/{$row['id']}/reset")->assertOk();
        $this->assertStringContainsString('Ndugu {guardian_name}', MessageTemplate::findOrFail(MessageTemplate::where('uuid', $row['id'])->value('id'))->body);
    }

    public function test_automatic_messages_cannot_be_deleted_but_custom_ones_can_have_a_language(): void
    {
        $list = $this->getJson('/api/v1/message-templates')->assertOk()->json('data');
        $system = collect($list)->first(fn ($t) => $t['is_system']);

        $this->deleteJson("/api/v1/message-templates/{$system['id']}")->assertUnprocessable();

        $custom = $this->postJson('/api/v1/message-templates', ['name' => 'Sports day', 'category' => 'general', 'body' => 'Siku ya michezo ni Ijumaa.', 'locale' => 'sw'])->assertCreated()->assertJsonPath('data.locale', 'sw')->assertJsonPath('data.is_system', false);
        $this->deleteJson('/api/v1/message-templates/'.$custom->json('data.id'))->assertOk();
    }

    public function test_the_invoice_can_be_resent_or_used_as_a_reminder_using_the_templates(): void
    {
        $this->feeSetup();
        $this->admit(['locale' => 'sw'])->assertCreated();
        $invoice = Invoice::firstOrFail();

        $this->postJson("/api/v1/invoices/{$invoice->uuid}/send-sms")->assertOk();
        $this->assertStringContainsString('salio TZS 850,000', SmsLog::latest('id')->first()->message);

        $this->postJson("/api/v1/invoices/{$invoice->uuid}/send-sms", ['type' => 'reminder'])->assertOk();
        $this->assertStringContainsString('kumbusho', SmsLog::latest('id')->first()->message);

        // Portal reminder button uses the same template.
        $this->actingAs($this->admin)->post("/invoices/{$invoice->uuid}/reminder")->assertRedirect();
        $this->assertStringContainsString('kumbusho', SmsLog::latest('id')->first()->message);
    }

    public function test_a_partial_payment_texts_the_parent_what_they_paid_and_what_remains(): void
    {
        $this->feeSetup();
        $this->admit()->assertCreated();
        $invoice = Invoice::firstOrFail();

        $this->postJson("/api/v1/invoices/{$invoice->uuid}/payments", [
            'amount' => 300000, 'method' => 'cash', 'paid_on' => '2026-02-01',
        ])->assertOk();

        $receipt = \App\Models\Payment::where('invoice_id', $invoice->id)->value('receipt_number');

        $sms = SmsLog::latest('id')->first();
        $this->assertSame('fees', $sms->category);
        $this->assertSame('255712345678', $sms->recipient_phone);
        $this->assertStringContainsString('Dear Asha Kileo', $sms->message);
        $this->assertStringContainsString('we have received TZS 300,000', $sms->message);
        $this->assertStringContainsString('receipt '.$receipt, $sms->message);
        $this->assertStringContainsString('Balance remaining: TZS 550,000', $sms->message);

        // Pay the rest: the balance is now zero, and the message says so.
        $this->postJson("/api/v1/invoices/{$invoice->uuid}/payments", [
            'amount' => 550000, 'method' => 'cash', 'paid_on' => '2026-02-10',
        ])->assertOk();

        $final = SmsLog::latest('id')->first();
        $this->assertStringContainsString('we have received TZS 550,000', $final->message);
        $this->assertStringContainsString('Balance remaining: TZS 0', $final->message);

        // One admission SMS + two payment SMS.
        $this->assertSame(3, SmsLog::count());
    }

    public function test_a_payment_still_succeeds_when_the_parent_has_no_phone_on_file(): void
    {
        $this->feeSetup();
        $this->admit()->assertCreated();
        $invoice = Invoice::firstOrFail();

        Guardian::where('school_id', $this->school->id)->update(['phone' => '']);

        $this->postJson("/api/v1/invoices/{$invoice->uuid}/payments", [
            'amount' => 50000, 'method' => 'cash', 'paid_on' => '2026-02-01',
        ])->assertOk()->assertJsonPath('data.status', 'partial');

        // Only the admission SMS went out — the payment one was skipped, not failed.
        $this->assertSame(1, SmsLog::count());
    }

    public function test_the_login_message_for_a_parent_follows_their_language_too(): void
    {
        $guardian = Guardian::create(['school_id' => $this->school->id, 'name' => 'Asha Kileo', 'phone' => '0712345678', 'locale' => 'sw']);

        $this->postJson("/api/v1/guardians/{$guardian->id}/account", ['email' => 'asha@parent.test'])->assertCreated();

        $text = SmsLog::firstOrFail()->message;
        $this->assertStringContainsString('akaunti yako ya ElimuGrid', $text);
        $this->assertStringContainsString('asha@parent.test', $text);
    }

    public function test_the_school_can_choose_its_message_language_in_settings(): void
    {
        $this->actingAs($this->admin)->put('/settings/school', ['name' => 'Sunrise Academy', 'timezone' => 'Africa/Dar_es_Salaam', 'message_locale' => 'sw'])->assertRedirect();

        $this->assertSame('sw', $this->school->fresh()->settings['message_locale']);
    }
}
