<?php

namespace App\Mail;

use App\Models\Payslip;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class PayslipMail extends Mailable
{
    public function __construct(public Payslip $payslip)
    {
    }

    private function fill(string $template): string
    {
        $p = $this->payslip;

        return strtr($template, [
            '{name}' => $p->name,
            '{emp_code}' => $p->emp_code,
            '{month}' => $p->run->periodLabel(),
            '{amount}' => inr($p->total_amount, 0),
            '{company}' => Setting::get('company_name'),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->fill((string) Setting::get('mail_subject')));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.payslip', with: ['body' => $this->fill((string) Setting::get('mail_body'))]);
    }

    public function attachments(): array
    {
        $p = $this->payslip;
        $pdf = Pdf::loadView('payslips.pdf', ['payslips' => collect([$p]), 'run' => $p->run, 's' => Setting::allValues()])->setPaper('a4')->output();
        $name = 'Payslip-'.Str::slug($p->emp_code.'-'.$p->name).'-'.$p->run->year.'-'.str_pad($p->run->month, 2, '0', STR_PAD_LEFT).'.pdf';

        return [Attachment::fromData(fn () => $pdf, $name)->withMime('application/pdf')];
    }
}
