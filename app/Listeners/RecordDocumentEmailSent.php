<?php

namespace App\Listeners;

use App\Models\EmailEvent;
use App\Models\Invoice;
use App\Models\Quote;
use App\Support\DocumentEmailHistory;
use Illuminate\Mail\Events\MessageSent;

class RecordDocumentEmailSent
{
    public function handle(MessageSent $event): void
    {
        $headers = $event->message->getHeaders();
        $attempt = $headers->get('X-CuciNow-Delivery-Attempt')?->getBodyAsString();
        if (! $attempt) {
            return;
        }

        $queued = EmailEvent::where('event_key', hash('sha256', $attempt.'|app.queued'))->first();
        if (! $queued) {
            return;
        }

        $providerId = $headers->get('X-Resend-Email-ID')?->getBodyAsString();
        $isPreview = in_array($queued->provider, ['log', 'array'], true);
        app(DocumentEmailHistory::class)->record(
            $attempt, $isPreview ? 'app.previewed' : 'app.sent', $queued->recipient,
            $queued->metadata, $queued->provider, $providerId,
        );

        if ($providerId) {
            $queued->update(['provider_email_id' => $providerId]);
        }

        if ($isPreview) {
            return;
        }

        $metadata = $queued->metadata;
        if (($metadata['category'] ?? null) === 'quotation') {
            // Do not overwrite accepted/rejected workflow decisions while a job was queued.
            Quote::whereKey($metadata['quote_id'])->whereIn('status', ['draft', 'sent'])
                ->update(['status' => 'sent', 'sent_at' => now()]);
        }
        if (($metadata['category'] ?? null) === 'invoice') {
            Invoice::whereKey($metadata['invoice_id'])->update(['sent_at' => now()]);
            Invoice::whereKey($metadata['invoice_id'])->where('status', 'draft')->update(['status' => 'sent']);
        }
    }
}
