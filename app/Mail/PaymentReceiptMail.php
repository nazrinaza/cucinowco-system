<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class PaymentReceiptMail extends CuciNowMailable
{
    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return $this->brandedEnvelope(
            "Payment receipt {$this->payment->payment_number}",
            ['category' => 'payment_receipt', 'payment_id' => $this->payment->id],
        );
    }

    public function content(): Content
    {
        return $this->brandedContent('emails.payment-receipt', implode("\n", [
            'Payment received. Thank you.',
            "Receipt: {$this->payment->payment_number}",
            "Invoice: {$this->payment->invoice->invoice_number}",
            'Paid on: '.$this->payment->paid_at->format('d M Y, g:i A'),
            'Method: '.str($this->payment->method)->replace('_', ' ')->title(),
            'Amount: RM '.number_format($this->payment->amount, 2),
            'Remaining invoice balance: RM '.number_format($this->payment->invoice->balance, 2),
        ]));
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("payment-{$this->payment->id}");
    }
}
