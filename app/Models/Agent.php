<?php

namespace App\Models;

use App\Mail\AgentAccessMail;
use App\Support\MalaysianBanks;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class Agent extends Authenticatable
{
    protected $fillable = ['name', 'email', 'phone', 'location', 'password', 'requested_code', 'social_links', 'terms_accepted_at'];

    protected $hidden = ['password', 'remember_token', 'hq_notes', 'payout_account_number'];

    /** Codes that would clash with built-in referral channels. */
    public const RESERVED_CODES = ['direct', 'admin', 'newsletter', 'instagram', 'threads', 'agents'];

    /** Validation for a requested referral code; shared by registration and the live availability check. */
    public static function referralCodeRules(): array
    {
        return ['required', 'regex:/^[a-z0-9][a-z0-9_-]{2,39}$/', Rule::notIn(self::RESERVED_CODES), 'unique:agents,requested_code', 'unique:referral_sources,code'];
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'social_links' => 'array', 'email_verified_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'approved_at' => 'datetime', 'last_login_at' => 'datetime', 'commission_rate_override' => 'decimal:2', 'payout_account_number' => 'encrypted', 'payout_details_updated_at' => 'datetime'];
    }

    public function hasPayoutDetails(): bool
    {
        return filled($this->payout_bank) && filled($this->payout_account_name) && filled($this->payout_account_number);
    }

    public function payoutBankName(): ?string
    {
        return MalaysianBanks::label($this->payout_bank);
    }

    /** e.g. "•••• 6789", for screens the agent sees. HQ sees the full number to make the transfer. */
    public function maskedPayoutAccountNumber(): ?string
    {
        return $this->payout_account_number ? '•••• '.substr($this->payout_account_number, -4) : null;
    }

    public function referralSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class);
    }

    public function introducerSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class, 'introducer_source_id');
    }

    public function commissionPlan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function effectiveCommissionPlan(): ?CommissionPlan
    {
        return $this->commissionPlan ?? CommissionPlan::default();
    }

    public function effectiveCommissionRate(): string
    {
        return (string) ($this->commission_rate_override ?? $this->effectiveCommissionPlan()?->rate_percent ?? '0.00');
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
