<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\AgentActivity;
use App\Models\AgentMedia;
use App\Support\AgentReporting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function dashboard(AgentReporting $report)
    {
        $agent = Auth::guard('agent')->user();

        return view('agents.dashboard', [
            'agent' => $agent, 'summary' => $report->summary($agent),
            'leads' => $report->leads($agent)->with(['quote.booking', 'quote.invoice'])->latest()->limit(5)->get(),
            'media' => AgentMedia::available()->latest()->limit(3)->get(), 'report' => $report,
        ]);
    }

    public function leads(AgentReporting $report)
    {
        return view('agents.leads', [
            'leads' => $report->leads(Auth::guard('agent')->user())->with(['quote.booking', 'quote.invoice'])->latest()->paginate(20),
            'report' => $report,
        ]);
    }

    public function analytics(Request $request, AgentReporting $report)
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:from']]);
        $end = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::today();
        $start = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $end->subDays(29);
        abort_if($start->gt($end) || $start->diffInDays($end) > 365, 422, 'Choose a date range of up to one year.');

        return view('agents.analytics', [...$report->analytics(Auth::guard('agent')->user(), $start, $end), 'start' => $start, 'end' => $end]);
    }

    public function media(Request $request)
    {
        $category = $request->validate(['category' => ['nullable', 'string', 'max:40']])['category'] ?? null;

        return view('agents.media', ['media' => AgentMedia::available()->when($category, fn ($q) => $q->where('category', $category))->latest()->paginate(12)->withQueryString()]);
    }

    public function download(AgentMedia $media)
    {
        abort_unless(AgentMedia::available()->whereKey($media->id)->exists() && $media->path, 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);
        AgentActivity::create(['agent_id' => Auth::guard('agent')->id(), 'action' => 'media.downloaded', 'details' => ['media_id' => $media->id]]);

        return Storage::disk('local')->download($media->path, $media->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(AgentMedia $media)
    {
        abort_unless(AgentMedia::available()->whereKey($media->id)->exists() && in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp']), 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return Storage::disk('local')->response($media->path, null, ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function account()
    {
        return view('agents.account', ['agent' => Auth::guard('agent')->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'min:9', 'max:30'],
            'location' => ['required', 'string', 'max:120'],
            'social_links' => ['nullable', 'array:website,instagram,facebook,threads,tiktok'],
            'social_links.*' => ['nullable', 'url:http,https', 'max:255'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        $agent = Auth::guard('agent')->user();
        if (! empty($data['password']) && ! Hash::check($data['current_password'], $agent->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }
        unset($data['current_password']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $agent->fill($data)->save();
        AgentActivity::create(['agent_id' => $agent->id, 'action' => 'profile.updated']);

        return back()->with('success', 'Your profile has been updated.');
    }
}
