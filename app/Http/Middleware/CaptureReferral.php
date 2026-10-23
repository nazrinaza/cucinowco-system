<?php

namespace App\Http\Middleware;

use App\Models\ReferralSource;
use App\Models\ReferralVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*', 'agents', 'agents/*') && ! $request->routeIs('agents.*')) {
            $this->capture($request);
        }

        return $next($request);
    }

    private function capture(Request $request): void
    {
        $requestedCode = Str::lower(trim((string) $request->query('ref', '')));
        $code = preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $requestedCode) ? $requestedCode : '';

        if ($code !== '') {
            $source = ReferralSource::query()->trackable()->where('code', $code)->first();
            if (! $source) {
                return;
            }

            $this->recordVisit($request, $source);

            $request->session()->put([
                'cucinow.referral_source_id' => $source->id,
                'cucinow.referral_code' => $source->code,
                'cucinow.referral_landing_url' => $request->fullUrl(),
                'cucinow.referral_landed_at' => now()->toDateTimeString(),
            ]);
            Cookie::queue(cookie(
                'cucinow_ref',
                $source->code,
                60 * 24 * 90,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));

            return;
        }

        if ($request->session()->has('cucinow.referral_source_id')) {
            return;
        }

        $cookieCode = Str::lower((string) $request->cookie('cucinow_ref', ''));
        if ($cookieCode === '') {
            return;
        }

        $source = ReferralSource::query()->trackable()->where('code', $cookieCode)->first();
        if ($source) {
            $request->session()->put([
                'cucinow.referral_source_id' => $source->id,
                'cucinow.referral_code' => $source->code,
            ]);
        }
    }

    private function recordVisit(Request $request, ReferralSource $source): void
    {
        if (! $request->isMethod('GET') || ! $request->routeIs('home', 'services.show') || Auth::guard('agent')->check() || Auth::guard('web')->check()) {
            return;
        }
        $ua = $request->userAgent() ?? '';
        if (preg_match('/bot|crawler|spider|facebookexternalhit|WhatsApp|preview|Headless/i', $ua)) {
            return;
        }
        $channel = $request->query('via', 'link');
        if (! is_string($channel) || ! in_array($channel, ['link', 'qr', 'whatsapp', 'instagram', 'threads', 'facebook', 'newsletter'])) {
            $channel = 'link';
        }
        DB::transaction(function () use ($request, $source, $ua, $channel) {
            $visitorToken = $request->session()->get('cucinow.visitor_token');
            if (! is_string($visitorToken) || $visitorToken === '') {
                $visitorToken = (string) Str::uuid();
                $request->session()->put('cucinow.visitor_token', $visitorToken);
            }
            $inserted = ReferralVisit::insertOrIgnore([
                'referral_source_id' => $source->id,
                'session_hash' => hash_hmac('sha256', $visitorToken, config('app.key')),
                'channel' => $channel,
                'landing_path' => $request->path() === '/' ? '/' : mb_substr('/'.$request->path(), 0, 255),
                'origin_host' => mb_substr((string) parse_url($request->header('referer', ''), PHP_URL_HOST), 0, 255) ?: null,
                'device' => preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : (preg_match('/Mobile|Android|iPhone/i', $ua) ? 'mobile' : 'desktop'),
                'created_at' => now(),
            ]);
            if ($inserted) {
                $source->increment('visit_count');
            }
        });
    }
}
