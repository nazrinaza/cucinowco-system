<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function store(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email'))), 'requested_code' => Str::lower(trim((string) $request->input('requested_code')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:agents,email'],
            'phone' => ['required', 'string', 'min:9', 'max:30'],
            'location' => ['required', 'string', 'max:120'],
            'requested_code' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{2,39}$/', Rule::notIn(['direct', 'admin', 'newsletter', 'instagram', 'threads', 'agents']), 'unique:agents,requested_code', 'unique:referral_sources,code'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ]);
        $agent = Agent::create([...collect($data)->except(['terms', 'password_confirmation', 'website'])->all(), 'terms_accepted_at' => now()]);
        AgentActivity::create(['agent_id' => $agent->id, 'action' => 'application.submitted']);
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
