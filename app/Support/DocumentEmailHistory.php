<?php

namespace App\Support;

use App\Mail\CuciNowMailable;
use App\Models\EmailEvent;
use App\Models\Invoice;
use App\Models\ProformaInvoice;
use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class DocumentEmailHistory
{
    public function queue(Quote|Invoice|ProformaInvoice $document, CuciNowMailable $mail, string $category, ?string $recipient = null, ?string $recipientName = null): void
    {
        $attempt = (string) Str::uuid();
        $metadata = [
            match (true) {
                $document instanceof Quote => 'quote_id',
                $document instanceof Invoice => 'invoice_id',
                default => 'proforma_invoice_id',
            } => (string) $document->id,
            'category' => $category,
            'delivery_attempt_id' => $attempt,
            'requested_by' => auth()->user()?->name ?? 'System',
        ];
        $recipient ??= $document->customer->email;
        $mail->deliveryAttemptId = $attempt;
        $mail->deliveryDocumentMetadata = array_intersect_key($metadata, ['quote_id' => true, 'invoice_id' => true, 'proforma_invoice_id' => true]);
        // Pin the mailer and attempt ID when queued so retries retain their identity.
        $mailer = config('mail.default');
        $mail->mailer($mailer);
        $this->record($attempt, 'app.queued', $recipient, $metadata, $mailer);

        try {
            Mail::to($recipient, $recipientName)->queue($mail);
        } catch (Throwable $exception) {
            $this->failed($attempt);
            throw $exception;
        }
    }

    public function record(string $attempt, string $type, string $recipient, array $metadata, string $provider, ?string $providerId = null): EmailEvent
    {
        return EmailEvent::firstOrCreate(['event_key' => hash('sha256', $attempt.'|'.$type)], [
            'provider' => $provider,
            'event_type' => $type,
            'provider_email_id' => $providerId,
            'recipient' => $recipient,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function failed(string $attempt): void
    {
        $queued = EmailEvent::where('event_key', hash('sha256', $attempt.'|app.queued'))->first();
        if ($queued) {
            $this->record($attempt, 'app.failed', $queued->recipient, $queued->metadata, $queued->provider);
        }
    }

    public function forDocument(Quote|Invoice|ProformaInvoice $document): LengthAwarePaginator
    {
        $key = match (true) {
            $document instanceof Quote => 'quote_id',
            $document instanceof Invoice => 'invoice_id',
            default => 'proforma_invoice_id',
        };
        $id = (string) $document->id;
        $providerIds = EmailEvent::where("metadata->{$key}", $id)
            ->whereNotNull('provider_email_id')->select('provider_email_id');

        return EmailEvent::where(fn ($query) => $query
            ->where("metadata->{$key}", $id)
            ->orWhereIn('provider_email_id', $providerIds))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(20, ['*'], 'email_page')->withQueryString()->fragment('email-history');
    }
}
