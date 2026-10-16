<?php

namespace App\Http\Middleware;

use App\Models\ReferralSource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*')) {
            $this->capture($request);
        }

        return $next($request);
    }

    private function capture(Request $request): void
    {
        $requestedCode = Str::lower(trim((string) $request->query('ref', '')));
        $code = preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $requestedCode) ? $requestedCode : '';

        if ($code !== '') {
            $source = ReferralSource::query()->where('code', $code)->where('is_active', true)->first();
            if (! $source) {
                return;
            }

            if ((int) $request->session()->get('cucinow.referral_source_id') !== $source->id) {
                $source->increment('visit_count');
            }

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

        $source = ReferralSource::query()->where('code', $cookieCode)->where('is_active', true)->first();
        if ($source) {
            $request->session()->put([
                'cucinow.referral_source_id' => $source->id,
                'cucinow.referral_code' => $source->code,
            ]);
        }
    }
}
