<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncBookingToGoogleCalendar;
use App\Jobs\SyncSiteVisitToGoogleCalendar;
use App\Models\Booking;
use App\Models\BookingCalendarSync;
use App\Models\GoogleCalendarConnection;
use App\Models\SiteVisitCalendarSync;
use App\Models\SiteVisitRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GoogleCalendarController extends Controller
{
    public function index(): View
    {
        $connection = GoogleCalendarConnection::active()->with('connectedBy')->latest('id')->first();
        $statusCounts = BookingCalendarSync::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $recentSyncs = BookingCalendarSync::with(['booking.customer'])
            ->latest('last_attempted_at')
            ->limit(15)
            ->get();
        $siteVisitStatusCounts = SiteVisitCalendarSync::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $recentSiteVisitSyncs = SiteVisitCalendarSync::with(['siteVisit.customer'])
            ->latest('last_attempted_at')
            ->limit(15)
            ->get();
        $recentCalendarSyncs = $recentSyncs->map(fn (BookingCalendarSync $sync): array => [
            'type' => 'Job',
            'reference' => $sync->booking?->booking_number ?? 'Removed booking',
            'customer' => $sync->booking?->customer?->name ?? '—',
            'status' => $sync->status,
            'last_attempted_at' => $sync->last_attempted_at,
            'last_error' => $sync->last_error,
            'retry_route' => $sync->booking ? route('admin.bookings.calendar.retry', $sync->booking) : null,
        ])->concat($recentSiteVisitSyncs->map(fn (SiteVisitCalendarSync $sync): array => [
            'type' => 'Site visit',
            'reference' => $sync->siteVisit?->reference_number ?? 'Removed site visit',
            'customer' => $sync->siteVisit?->customer?->name ?? '—',
            'status' => $sync->status,
            'last_attempted_at' => $sync->last_attempted_at,
            'last_error' => $sync->last_error,
            'retry_route' => $sync->siteVisit ? route('admin.site-visits.calendar.retry', $sync->siteVisit) : null,
        ]))->sortByDesc('last_attempted_at')->take(20)->values();

        return view('admin.integrations.google-calendar', compact(
            'connection',
            'statusCounts',
            'recentSyncs',
            'siteVisitStatusCounts',
            'recentSiteVisitSyncs',
            'recentCalendarSyncs',
        ));
    }

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return back()->with('error', 'Add the Google Calendar OAuth settings to the server .env first.');
        }

        $state = Str::random(64);
        $request->session()->put('google_calendar_oauth_state', $state);

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.calendar.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'openid',
                'email',
                'https://www.googleapis.com/auth/calendar.events',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);
        $expectedState = (string) $request->session()->pull('google_calendar_oauth_state');
        abort_unless($expectedState !== '' && hash_equals($expectedState, $data['state']), 403, 'Invalid OAuth state.');

        $tokenResponse = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.calendar.client_id'),
            'client_secret' => config('services.google.calendar.client_secret'),
            'code' => $data['code'],
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        if (! $tokenResponse->successful() || ! $tokenResponse->json('access_token') || ! $tokenResponse->json('refresh_token')) {
            return redirect()->route('admin.integrations.google-calendar.index')
                ->with('error', 'Google did not return an offline refresh token. Reconnect and grant consent again.');
        }

        $email = null;
        try {
            $userResponse = Http::acceptJson()
                ->withToken($tokenResponse->json('access_token'))
                ->timeout(15)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');
            $email = $userResponse->successful() ? $userResponse->json('email') : null;
        } catch (Throwable) {
            // The calendar connection remains valid even if the account label cannot be retrieved.
        }

        DB::transaction(function () use ($request, $tokenResponse, $email): void {
            GoogleCalendarConnection::active()->update([
                'access_token' => null,
                'refresh_token' => null,
                'disconnected_at' => now(),
            ]);
            GoogleCalendarConnection::create([
                'connected_by_user_id' => $request->user()->id,
                'google_email' => $email,
                'calendar_id' => config('services.google.calendar.calendar_id'),
                'access_token' => $tokenResponse->json('access_token'),
                'refresh_token' => $tokenResponse->json('refresh_token'),
                'token_expires_at' => now()->addSeconds(max(60, (int) $tokenResponse->json('expires_in', 3600))),
                'connected_at' => now(),
            ]);
        });

        return redirect()->route('admin.integrations.google-calendar.index')
            ->with('success', 'Company Google Calendar connected. No bookings were synced automatically.');
    }

    public function disconnect(): RedirectResponse
    {
        $connection = GoogleCalendarConnection::active()->latest('id')->first();
        if ($connection) {
            try {
                Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', [
                    'token' => $connection->refresh_token,
                ]);
            } catch (Throwable) {
                // Always remove local access even if Google is temporarily unavailable.
            }

            $connection->update([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'disconnected_at' => now(),
            ]);
        }

        return back()->with('success', 'Google Calendar disconnected. Existing Google events were not changed.');
    }

    public function syncUpcoming(): RedirectResponse
    {
        if (! GoogleCalendarConnection::active()->exists()) {
            return back()->with('error', 'Connect Google Calendar before starting a sync.');
        }

        $queued = 0;
        Booking::query()
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->where('scheduled_start', '>=', now())
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$queued): void {
                foreach ($bookings as $booking) {
                    BookingCalendarSync::updateOrCreate(
                        ['booking_id' => $booking->id],
                        ['status' => 'queued', 'last_error' => null],
                    );
                    SyncBookingToGoogleCalendar::dispatch($booking->id)->afterCommit();
                    $queued++;
                }
            });

        SiteVisitRequest::query()
            ->whereIn('status', ['scheduled', 'completed'])
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_end')
            ->where('scheduled_start', '>=', now())
            ->orderBy('id')
            ->chunkById(100, function ($siteVisits) use (&$queued): void {
                foreach ($siteVisits as $siteVisit) {
                    SiteVisitCalendarSync::updateOrCreate(
                        ['site_visit_request_id' => $siteVisit->id],
                        ['status' => 'queued', 'last_error' => null],
                    );
                    SyncSiteVisitToGoogleCalendar::dispatch($siteVisit->id)->afterCommit();
                    $queued++;
                }
            });

        return back()->with('success', number_format($queued).' upcoming confirmed appointment(s) and job(s) queued for idempotent sync.');
    }

    public function retry(Booking $booking): RedirectResponse
    {
        BookingCalendarSync::updateOrCreate(
            ['booking_id' => $booking->id],
            ['status' => 'queued', 'last_error' => null],
        );
        SyncBookingToGoogleCalendar::dispatch($booking->id)->afterCommit();

        return back()->with('success', "Calendar sync queued for {$booking->booking_number}.");
    }

    public function retrySiteVisit(SiteVisitRequest $siteVisit): RedirectResponse
    {
        SiteVisitCalendarSync::updateOrCreate(
            ['site_visit_request_id' => $siteVisit->id],
            ['status' => 'queued', 'last_error' => null],
        );
        SyncSiteVisitToGoogleCalendar::dispatch($siteVisit->id)->afterCommit();

        return back()->with('success', "Calendar sync queued for {$siteVisit->reference_number}.");
    }

    private function configured(): bool
    {
        return collect([
            config('services.google.calendar.client_id'),
            config('services.google.calendar.client_secret'),
            config('services.google.calendar.calendar_id'),
        ])->every(fn ($value): bool => filled($value));
    }

    private function redirectUri(): string
    {
        return config('services.google.calendar.redirect_uri')
            ?: route('admin.integrations.google-calendar.callback');
    }
}
