<?php

namespace App\Http\Middleware;

use App\Models\ReferralSource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CaptureAgentIntroducer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('agent')->check()) {
            $value = $request->query('ref');
            $code = is_string($value) ? strtolower(trim($value)) : '';
            if ($request->isMethod('GET') && preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $code)) {
                $source = ReferralSource::query()->trackable()->where('code', $code)->first();
                if ($source) {
                    $attribution = ['code' => $source->code, 'expires_at' => now()->addDays(config('agents.attribution_days'))->timestamp];
                    $request->session()->put('agent_introducer', $attribution);
                    Cookie::queue(cookie('cucinow_agent_introducer', json_encode($attribution), 60 * 24 * config('agents.attribution_days'), '/', null, $request->isSecure(), true, false, 'lax'));
                }
            }
            if (! $request->session()->has('agent_introducer')) {
                $cookie = $request->cookie('cucinow_agent_introducer');
                $attribution = is_string($cookie) ? json_decode($cookie, true) : null;
                if (is_array($attribution)) {
                    $request->session()->put('agent_introducer', $attribution);
                }
            }
        }

        return $next($request);
    }
}
