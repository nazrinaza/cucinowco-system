<?php

namespace App\Mail;

use App\Support\DocumentEmailHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

abstract class CuciNowMailable extends Mailable
{
    use Queueable, SerializesModels;

    public ?string $deliveryAttemptId = null;

    public array $deliveryDocumentMetadata = [];

    public function failed(\Throwable $exception): void
    {
        if ($this->deliveryAttemptId) {
            app(DocumentEmailHistory::class)->failed($this->deliveryAttemptId);
        }
    }

    protected function brandedEnvelope(string $subject, array $metadata = []): Envelope
    {
        if ($this->deliveryAttemptId) {
            $metadata = [...$metadata, ...$this->deliveryDocumentMetadata];
            $metadata['delivery_attempt_id'] = $this->deliveryAttemptId;
        }

        return new Envelope(
            replyTo: [new Address(config('company.email'), config('company.name'))],
            subject: $subject,
            metadata: collect($metadata)->map(fn ($value) => (string) $value)->all(),
        );
    }

    protected function deliveryHeaders(string $idempotencyKey, array $additional = []): Headers
    {
        if ($this->deliveryAttemptId) {
            $idempotencyKey = 'document-'.$this->deliveryAttemptId;
            $additional['X-CuciNow-Delivery-Attempt'] = $this->deliveryAttemptId;
        }

        return new Headers(text: [
            'Resend-Idempotency-Key' => $idempotencyKey,
            ...$additional,
        ]);
    }
}
