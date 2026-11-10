<?php

namespace App\Jobs;

use App\Models\WhatsAppEnquiry;
use App\Services\MetaWhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppEnquiryAcknowledgement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(public WhatsAppEnquiry $enquiry)
    {
        $this->onQueue(config('services.whatsapp.queue', 'default'));
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MetaWhatsAppClient $client): void
    {
        if (! config('services.whatsapp.enabled') || ! $client->configured()) {
            $this->enquiry->update(['status' => 'saved', 'provider_status' => 'not_configured']);

            return;
        }

        $this->enquiry->update(['provider_status' => 'submitting', 'failure_reason' => null]);
        $result = $client->sendEnquiryAcknowledgement($this->enquiry->phone, $this->enquiry->name, $this->enquiry->reference_number);

        $this->enquiry->update([
            'status' => 'acknowledgement_queued',
            'provider_status' => 'accepted',
            'provider_message_id' => $result['id'],
            'submitted_to_meta_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->enquiry->update([
            'status' => 'saved',
            'provider_status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => str($exception?->getMessage() ?: 'WhatsApp delivery failed')->limit(500),
        ]);
    }
}
