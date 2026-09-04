<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

abstract class CuciNowMailable extends Mailable
{
    use Queueable, SerializesModels;

    protected function brandedEnvelope(string $subject, array $metadata = []): Envelope
    {
        return new Envelope(
            replyTo: [new Address(config('company.email'), config('company.name'))],
            subject: $subject,
            metadata: collect($metadata)->map(fn ($value) => (string) $value)->all(),
        );
    }

    protected function deliveryHeaders(string $idempotencyKey, array $additional = []): Headers
    {
        return new Headers(text: [
            'Resend-Idempotency-Key' => $idempotencyKey,
            ...$additional,
        ]);
    }
}
