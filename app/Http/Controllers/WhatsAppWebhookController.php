<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppEnquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge'));
        $configuredToken = (string) config('services.whatsapp.webhook_verify_token');

        abort_unless($mode === 'subscribe' && $configuredToken !== '' && hash_equals($configuredToken, $token), 403);

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): Response
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        abort_unless($secret !== '' && $signature !== '' && hash_equals($expected, $signature), 403);

        foreach ((array) data_get($request->json()->all(), 'entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.statuses', []) as $status) {
                    $this->recordStatus($status);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    /** @param array<string, mixed> $status */
    private function recordStatus(array $status): void
    {
        $messageId = data_get($status, 'id');
        $state = data_get($status, 'status');

        if (! is_string($messageId) || ! is_string($state)) {
            return;
        }

        $enquiry = WhatsAppEnquiry::query()->where('provider_message_id', $messageId)->first();

        if (! $enquiry) {
            return;
        }

        $occurredAt = is_numeric(data_get($status, 'timestamp'))
            ? Carbon::createFromTimestampUTC((int) data_get($status, 'timestamp'))
            : now();
        $updates = ['provider_status' => $state];

        if (in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
            $updates[$state.'_at'] = $occurredAt;
        }

        if ($state === 'failed') {
            $updates['failure_reason'] = str((string) data_get($status, 'errors.0.title', 'Delivery failed'))->limit(500);
        }

        $enquiry->update($updates);
    }
}
