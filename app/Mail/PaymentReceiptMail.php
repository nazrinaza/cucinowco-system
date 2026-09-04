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
        return new Content(view: 'emails.payment-receipt');
    }

    public function headers(): Headers
    {
        return $this->deliveryHeaders("payment-{$this->payment->id}");
    }
}
