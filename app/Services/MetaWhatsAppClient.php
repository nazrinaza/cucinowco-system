<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsAppClient
{
    public function configured(): bool
    {
        return filled(config('services.whatsapp.access_token'))
            && filled(config('services.whatsapp.phone_number_id'))
            && filled(config('services.whatsapp.template.name'));
    }

    /** @return array{id: string} */
    public function sendEnquiryAcknowledgement(string $phone, string $name, string $reference): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('WhatsApp Cloud API is not configured.');
        }

        $response = $this->request()->post($this->endpoint(), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => preg_replace('/\D/', '', $phone),
            'type' => 'template',
            'template' => [
                'name' => config('services.whatsapp.template.name'),
                'language' => ['code' => config('services.whatsapp.template.language', 'en')],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $name],
                        ['type' => 'text', 'text' => $reference],
                    ],
                ]],
            ],
        ]);

        $response->throw();
        $messageId = data_get($response->json(), 'messages.0.id');

        if (! is_string($messageId) || $messageId === '') {
            throw new RuntimeException('Meta did not return a WhatsApp message ID.');
        }

        return ['id' => $messageId];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken((string) config('services.whatsapp.access_token'))
            ->timeout(15)
            ->retry(2, 500, throw: false);
    }

    private function endpoint(): string
    {
        $version = trim((string) config('services.whatsapp.api_version'));
        $phoneNumberId = trim((string) config('services.whatsapp.phone_number_id'));

        if ($version === '' || $phoneNumberId === '') {
            throw new RuntimeException('WhatsApp API version or phone number ID is missing.');
        }

        return "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";
    }
}
