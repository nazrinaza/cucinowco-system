<?php

namespace App\Listeners;

use App\Models\EmailEvent;
use App\Models\NewsletterCampaign;
use App\Models\Subscriber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class HandleResendEmailEvent
{
    public function handle(object $event): void
    {
        $payload = $event->payload ?? null;

        if (! is_array($payload) || ! isset($payload['type'])) {
            return;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $type = (string) $payload['type'];
        $emailId = isset($data['email_id']) ? (string) $data['email_id'] : null;
        $eventKey = hash('sha256', implode('|', [
            $type,
            $emailId ?? '',
            (string) ($payload['created_at'] ?? $data['created_at'] ?? ''),
        ]));
        $metadata = $this->normaliseTags($data['tags'] ?? []);
        $recipient = is_array($data['to'] ?? null) ? ($data['to'][0] ?? null) : ($data['to'] ?? null);

        DB::transaction(function () use ($eventKey, $type, $emailId, $recipient, $metadata, $payload): void {
            $emailEvent = EmailEvent::firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'provider' => 'resend',
                    'event_type' => $type,
                    'provider_email_id' => $emailId,
                    'recipient' => $recipient,
                    'metadata' => $metadata,
                    'occurred_at' => $this->eventTime($payload),
                ],
            );

            if (! $emailEvent->wasRecentlyCreated) {
                return;
            }

            $campaignId = filter_var($metadata['campaign_id'] ?? null, FILTER_VALIDATE_INT);
            if ($campaignId) {
                $counter = match ($type) {
                    'email.opened' => 'open_count',
                    'email.clicked' => 'click_count',
                    'email.bounced', 'email.complained', 'email.suppressed', 'email.failed' => 'bounce_count',
                    default => null,
                };

                if ($counter) {
                    NewsletterCampaign::whereKey($campaignId)->increment($counter);
                }
            }

            $subscriberId = filter_var($metadata['subscriber_id'] ?? null, FILTER_VALIDATE_INT);
            if ($subscriberId && in_array($type, ['email.bounced', 'email.failed'], true)) {
                Subscriber::whereKey($subscriberId)->update(['status' => 'bounced']);
            }

            if ($subscriberId && in_array($type, ['email.complained', 'email.suppressed'], true)) {
                Subscriber::whereKey($subscriberId)->update([
                    'status' => 'unsubscribed',
                    'unsubscribed_at' => now(),
                ]);
            }
        });
    }

    private function normaliseTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        if (array_is_list($tags)) {
            return collect($tags)
                ->filter(fn ($tag) => is_array($tag) && isset($tag['name'], $tag['value']))
                ->mapWithKeys(fn ($tag) => [(string) $tag['name'] => (string) $tag['value']])
                ->all();
        }

        return collect($tags)->map(fn ($value) => (string) $value)->all();
    }

    private function eventTime(array $payload): ?CarbonImmutable
    {
        $value = $payload['created_at'] ?? data_get($payload, 'data.created_at');

        return $value ? CarbonImmutable::parse($value) : null;
    }
}
