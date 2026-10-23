<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AgentAccessMail;
use App\Models\Agent;
use App\Models\AgentActivity;
use App\Models\ReferralSource;
use App\Support\AgentReporting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'suspended', 'rejected'])], 'q' => ['nullable', 'string', 'max:120']]);
        $agents = Agent::with('referralSource')
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('requested_code', 'like', "%{$term}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.agents.index', ['agents' => $agents, 'counts' => Agent::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')]);
    }

    public function show(Agent $agent, AgentReporting $report)
    {
        return view('admin.agents.show', [
            'agent' => $agent, 'summary' => $report->summary($agent),
            'sources' => ReferralSource::where('channel', 'agent')->whereDoesntHave('agent')->orderBy('name')->get(),
            'activities' => AgentActivity::where('agent_id', $agent->id)->latest('id')->limit(30)->get(),
            'leads' => $report->leads($agent)->with('customer')->latest()->paginate(15),
        ]);
    }

    public function update(Request $request, Agent $agent)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'suspended', 'rejected'])],
            'hq_notes' => ['nullable', 'string', 'max:3000'],
            'referral_source_id' => ['nullable', 'integer', 'exists:referral_sources,id'],
        ]);
        $oldStatus = $agent->status;
        DB::transaction(function () use ($agent, $data, $request) {
            $agent = Agent::lockForUpdate()->findOrFail($agent->id);
            $source = $agent->referralSource;
            if ($data['status'] === 'approved') {
                if (! $agent->email_verified_at) {
                    throw ValidationException::withMessages(['status' => 'The agent must verify their email before approval.']);
                }
                if (! $source && ! empty($data['referral_source_id'])) {
                    $source = ReferralSource::where('channel', 'agent')->whereDoesntHave('agent')->lockForUpdate()->find($data['referral_source_id']);
                    if (! $source) {
                        throw ValidationException::withMessages(['referral_source_id' => 'Select an unassigned agent referral code.']);
                    }
                }
                if (! $source) {
                    if (ReferralSource::where('code', $agent->requested_code)->exists()) {
                        throw ValidationException::withMessages(['referral_source_id' => 'This code already exists. Link the correct existing source or resolve the conflict.']);
                    }
                    $source = ReferralSource::create(['name' => $agent->name, 'code' => $agent->requested_code, 'channel' => 'agent', 'is_active' => true, 'created_by_user_id' => $request->user()->id]);
                }
            }
            $source?->update(['is_active' => $data['status'] === 'approved']);
            $agent->forceFill([
                'status' => $data['status'], 'hq_notes' => $data['hq_notes'] ?? null,
                'referral_source_id' => $source?->id,
                'approved_at' => $data['status'] === 'approved' ? ($agent->approved_at ?? now()) : $agent->approved_at,
                'approved_by_user_id' => $data['status'] === 'approved' ? $request->user()->id : $agent->approved_by_user_id,
            ])->save();
            AgentActivity::create(['agent_id' => $agent->id, 'user_id' => $request->user()->id, 'action' => 'application.updated', 'details' => ['status' => $data['status'], 'referral_source_id' => $source?->id]]);
        });
        if ($oldStatus !== $data['status']) {
            Mail::to($agent->email, $agent->name)->queue(new AgentAccessMail(
                'CuciNow agent account update',
                'Your agent account status is now '.$data['status'].'. Sign in to see your account and next steps.',
                'Open agent portal', route('agents.account'),
            ));
        }

        return back()->with('success', 'Agent updated. Referral access follows the approval status.');
    }
}
