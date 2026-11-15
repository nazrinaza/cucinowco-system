<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentActivity;
use App\Models\CommissionPlan;
use App\Models\ReferralSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function login()
    {
        return Auth::guard('agent')->check() ? redirect()->route('agents.dashboard') : view('agents.auth', ['mode' => 'login']);
    }

    public function register()
    {
        return Auth::guard('agent')->check() ? redirect()->route('agents.account') : view('agents.auth', ['mode' => 'register']);
    }

    /** Public landing page explaining the programme. Uses live plan rates so the example never goes stale. */
    public function howItWorks()
    {
        $plan = CommissionPlan::default();
        $agent = Auth::guard('agent')->user();

        return view('agents.how-it-works', [
            'rate' => (float) ($plan?->rate_percent ?? 0),
            'introducerRate' => (float) ($plan?->introducer_rate_percent ?? 0),
            'basis' => $plan?->basis ?? 'net_sales',
            'exampleLink' => $agent?->referralSource?->tracking_url
                ?? rtrim(config('agents.public_url'), '/').'/?ref=yourname',
            'clients' => ['Maybank', 'CIMB', 'Tenaga Nasional', 'Proton', 'Perodua', 'MRT', 'J&T Express', 'UiTM', 'University of Malaya', 'JKR'],
        ]);
    }

    /** Live "is this referral code free?" check for the registration form. */
    public function checkCode(Request $request): JsonResponse
    {
        $code = Str::lower(trim((string) $request->query('code')));
        $validator = Validator::make(['requested_code' => $code], ['requested_code' => Agent::referralCodeRules()], [
            'requested_code.required' => 'Enter a referral code.',
            'requested_code.regex' => 'Use 3–40 lowercase letters, numbers, hyphens or underscores, starting with a letter or number.',
            'requested_code.not_in' => 'This code is reserved. Please choose another.',
            'requested_code.unique' => 'This code is already taken. Please choose another.',
        ]);

        return response()->json([
            'code' => $code,
            'available' => $validator->passes(),
            'message' => $validator->errors()->first('requested_code') ?: 'Great, this code is available.',
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email'))), 'requested_code' => Str::lower(trim((string) $request->input('requested_code')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:agents,email'],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'location' => ['required', 'string', 'max:120'],
            'requested_code' => Agent::referralCodeRules(),
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ]);
        $agent = Agent::create([...collect($data)->except(['terms', 'password_confirmation', 'website'])->all(), 'terms_accepted_at' => now()]);
        // Resolve again at submission: suspended/deactivated sources cannot claim registrations.
        $attribution = $request->session()->get('agent_introducer', []);
        $source = is_array($attribution) && ($attribution['expires_at'] ?? 0) > now()->timestamp && is_string($attribution['code'] ?? null)
            ? ReferralSource::query()->trackable()->where('code', $attribution['code'])->first()
            : null;
        $agent->forceFill([
            'introducer_source_id' => $source?->id,
            'introducer_code' => $source?->code ?? 'direct',
            'introducer_name' => $source?->name,
            'introducer_channel' => $source?->channel,
        ])->save();
        AgentActivity::create(['agent_id' => $agent->id, 'action' => 'application.submitted', 'details' => ['introducer_code' => $agent->introducer_code]]);
        $request->session()->forget('agent_introducer');
        Cookie::queue(Cookie::forget('cucinow_agent_introducer'));
        $agent->sendVerificationEmail();
        Auth::guard('agent')->login($agent);
        $request->session()->regenerate();

        return redirect()->route('agents.account')->with('success', 'Application received. Check your inbox to verify your email, then HQ will review your application.');
    }

    public function authenticate(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $data['email'] = Str::lower(trim($data['email']));
        if (! Auth::guard('agent')->attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        Auth::guard('agent')->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('agents.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('agent')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('agents.login');
    }

    public function verify(Request $request, Agent $agent, string $hash)
    {
        abort_unless(hash_equals(sha1($agent->email), $hash), 403);
        if (! $agent->email_verified_at) {
            $agent->forceFill(['email_verified_at' => now()])->save();
            AgentActivity::create(['agent_id' => $agent->id, 'action' => 'email.verified']);
        }

        return redirect()->route('agents.login')->with('success', 'Email verified. Sign in to check your HQ approval status.');
    }

    public function resend()
    {
        $agent = Auth::guard('agent')->user();
        if (! $agent->email_verified_at) {
            $agent->sendVerificationEmail();
        }

        return back()->with('success', 'A verification email has been queued if your email is still unverified.');
    }

    public function forgot()
    {
        return view('agents.auth', ['mode' => 'forgot']);
    }

    public function emailReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('agents')->sendResetLink(['email' => Str::lower(trim($request->input('email')))]);

        return back()->with('success', 'If an agent account exists, a password reset link will be emailed to it.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('agents.auth', ['mode' => 'reset', 'token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(12)]]);
        $status = Password::broker('agents')->reset($data, function (Agent $agent, string $password) {
            $agent->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            AgentActivity::create(['agent_id' => $agent->id, 'action' => 'password.reset']);
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('agents.login')->with('success', 'Password reset. You can sign in now.')
            : back()->withErrors(['email' => __($status)]);
    }
}
