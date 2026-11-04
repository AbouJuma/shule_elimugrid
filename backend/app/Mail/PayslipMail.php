<?php

namespace App\Mail;

use App\Models\Payslip;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A staff member's payslip for one month, with the PDF attached. */
class PayslipMail extends Mailable
{
    public function __construct(public Payslip $payslip, public School $school) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your payslip for :period — :school', [
            'period' => $this->payslip->run->periodLabel(),
            'school' => $this->school->name,
        ]));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e(__('Hello :name,', ['name' => $this->payslip->staffProfile->user->name])).'</p>'
            .'<p>'.e(__('Your payslip for :period is attached as a PDF.', ['period' => $this->payslip->run->periodLabel()])).'</p>'
            .'<p>'.e($this->school->name).'</p>');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $binary = Pdf::loadView('payroll.payslip', ['payslip' => $this->payslip, 'school' => $this->school])
            ->setPaper('a4', 'portrait')
            ->output();

        return [
            Attachment::fromData(fn () => $binary, "payslip-{$this->payslip->staffProfile->employee_number}-{$this->payslip->run->period}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
