<?php

namespace App\Services\GoogleCalendar;

use App\Exceptions\GoogleCalendarException;
use App\Models\GoogleCalendarConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleCalendarClient
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    public function createEvent(GoogleCalendarConnection $connection, string $eventId, array $payload): void
    {
        $response = $this->request($connection)
            ->post($this->eventsUrl($connection), ['id' => $eventId, ...$payload]);

        if ($response->status() === 409) {
            $this->updateEvent($connection, $eventId, $payload);

            return;
        }

        $this->ensureSuccessful($response, 'create');
    }

    public function updateEvent(GoogleCalendarConnection $connection, string $eventId, array $payload): void
    {
        $response = $this->request($connection)
            ->put($this->eventUrl($connection, $eventId), $payload);

        if ($response->status() === 404 || $response->status() === 410) {
            $this->createEvent($connection, $eventId, $payload);

            return;
        }

        $this->ensureSuccessful($response, 'update');
    }

    public function deleteEvent(GoogleCalendarConnection $connection, string $eventId): void
    {
        $response = $this->request($connection)->delete($this->eventUrl($connection, $eventId));

        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        $this->ensureSuccessful($response, 'delete');
    }

    private function request(GoogleCalendarConnection $connection): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken($connection))
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    private function accessToken(GoogleCalendarConnection $connection): string
    {
        if ($connection->access_token && $connection->token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        return Cache::lock('google-calendar-token-refresh', 30)->block(10, function () use ($connection): string {
            $connection->refresh();
            if ($connection->access_token && $connection->token_expires_at?->isAfter(now()->addMinute())) {
                return $connection->access_token;
            }

            if (! $connection->refresh_token) {
                throw new GoogleCalendarException('Google Calendar connection has no refresh token. Reconnect the account.');
            }

            $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.calendar.client_id'),
                'client_secret' => config('services.google.calendar.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new GoogleCalendarException('Google Calendar token refresh failed. Reconnect the account.');
            }

            $connection->update([
                'access_token' => $response->json('access_token'),
                'token_expires_at' => now()->addSeconds(max(60, (int) $response->json('expires_in', 3600))),
            ]);

            return $connection->access_token;
        });
    }

    private function eventsUrl(GoogleCalendarConnection $connection): string
    {
        return self::API_BASE.'/calendars/'.rawurlencode($connection->calendar_id).'/events';
    }

    private function eventUrl(GoogleCalendarConnection $connection, string $eventId): string
    {
        return $this->eventsUrl($connection).'/'.rawurlencode($eventId);
    }

    private function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = (string) ($response->json('error.message') ?: "HTTP {$response->status()}");
        throw new GoogleCalendarException("Google Calendar could not {$action} the event: ".str($reason)->limit(300));
    }
}
