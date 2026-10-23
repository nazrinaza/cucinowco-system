<?php

namespace App\Models;

use App\Mail\AgentAccessMail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class Agent extends Authenticatable
{
    protected $fillable = ['name', 'email', 'phone', 'location', 'password', 'requested_code', 'social_links', 'terms_accepted_at'];

    protected $hidden = ['password', 'remember_token', 'hq_notes'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'social_links' => 'array', 'email_verified_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'approved_at' => 'datetime', 'last_login_at' => 'datetime'];
    }

    public function referralSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class);
    }

    public function sendVerificationEmail(): void
    {
        $url = URL::temporarySignedRoute('agents.verify', now()->addMinutes(60), ['agent' => $this->id, 'hash' => sha1($this->email)]);
        Mail::to($this->email, $this->name)->queue(new AgentAccessMail(
            'Verify your CuciNow agent email',
            "Hello {$this->name}, please verify your email address. HQ will review your application before activating your referral link.",
            'Verify email', $url,
        ));
    }

    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email, $this->name)->queue(new AgentAccessMail(
            'Reset your CuciNow agent password',
            'We received a request to reset your password. This link expires in 60 minutes. If you did not request it, no action is needed.',
            'Reset password', route('agents.password.reset', ['token' => $token, 'email' => $this->email]),
        ));
    }
}
