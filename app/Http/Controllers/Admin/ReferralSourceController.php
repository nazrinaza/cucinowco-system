<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReferralSourceController extends Controller
{
    private const CHANNELS = [
        'agent' => 'Agent',
        'newsletter' => 'Newsletter',
        'instagram' => 'Instagram',
        'threads' => 'Threads',
        'campaign' => 'Campaign',
        'partner' => 'Partner',
        'other' => 'Other',
    ];

    public function index(Request $request): View
    {
        $sources = ReferralSource::query()
            ->with('createdBy:id,name')
            ->withCount('siteVisitRequests')
            ->latest()
            ->paginate(20);

        return view('admin.referrals.index', [
            'sources' => $sources,
            'channels' => self::CHANNELS,
            'summary' => [
                'active' => ReferralSource::where('is_active', true)->count(),
                'visits' => ReferralSource::sum('visit_count'),
                'leads' => ReferralSource::sum('lead_count'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:80'],
            'channel' => ['required', Rule::in(array_keys(self::CHANNELS))],
        ]);

        $code = Str::of($data['code'] ?: $data['name'])->lower()->slug('-')->toString();
        if ($code === '' || $code === 'direct') {
            throw ValidationException::withMessages(['code' => 'Enter a unique tracking code other than “direct”.']);
        }

        $request->merge(['normalized_code' => $code]);
        $request->validate(['normalized_code' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('referral_sources', 'code')]]);

        ReferralSource::create([
            'name' => $data['name'],
            'code' => $code,
            'channel' => $data['channel'],
            'is_active' => true,
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Referral QR created. Preview or download it below.');
    }

    public function update(Request $request, ReferralSource $referral): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if ($data['is_active'] && $referral->agent && $referral->agent->status !== 'approved') {
            throw ValidationException::withMessages([
                'is_active' => 'Approve this agent from the Agents page before activating their referral code.',
            ]);
        }
        $referral->update($data);

        return back()->with('success', $referral->is_active ? 'Referral activated.' : 'Referral paused. Existing lead records are preserved.');
    }
}
