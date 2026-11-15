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

    /**
     * Social profiles: label, the prefix shown in front of the input, and the URL the handle becomes.
     * Full URLs are stored, so links elsewhere keep working; agents only type the handle.
     */
    public const SOCIALS = [
        'website' => ['Website', 'https://', 'https://%s'],
        'instagram' => ['Instagram', '@', 'https://instagram.com/%s'],
        'tiktok' => ['TikTok', '@', 'https://www.tiktok.com/@%s'],
        'threads' => ['Threads', '@', 'https://www.threads.net/@%s'],
        'x' => ['X (Twitter)', '@', 'https://x.com/%s'],
        'youtube' => ['YouTube', 'youtube.com/@', 'https://www.youtube.com/@%s'],
        'facebook' => ['Facebook', 'fb.com/', 'https://facebook.com/%s'],
    ];

    /** Turn whatever the agent typed (handle, @handle or a pasted URL) into a stored URL, or null. */
    public static function socialUrl(string $key, ?string $input): ?string
    {
        $value = trim((string) $input);
        if ($value === '') {
            return null;
        }
        if ($key === 'website') {
            return 'https://'.preg_replace('#^https?://#i', '', $value);
        }
        // Pasted profile URL: keep only the handle part after the host (and any leading @).
        $value = preg_replace('#^(https?://)?(www\.|m\.)?[a-z0-9.-]+\.[a-z]{2,}/#i', '', $value);
        $value = trim(explode('?', $value)[0], "/@ \t");

        return $value === '' ? null : sprintf(self::SOCIALS[$key][2], $value);
    }

    /** The handle to show back in the input, e.g. "bell.naza" from https://instagram.com/bell.naza. */
    public function socialHandle(string $key): string
    {
        $url = (string) ($this->social_links[$key] ?? '');
        if ($url === '') {
            return '';
        }
        if ($key === 'website') {
            return preg_replace('#^https?://#i', '', $url);
        }

        return trim(preg_replace('#^(https?://)?(www\.|m\.)?[a-z0-9.-]+\.[a-z]{2,}/#i', '', $url), '/@');
    }

    /** Malaysian number without +60 or the leading 0, for the input next to the +60 prefix. */
    public function phoneLocal(): string
    {
        return ltrim(preg_replace('/^60/', '', preg_replace('/\D+/', '', (string) $this->phone)), '0');
    }

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
