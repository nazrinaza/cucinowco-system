<?php

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentActivity;
use App\Models\AgentMedia;
use App\Models\AgentMediaFolder;
use App\Support\AgentCommissions;
use App\Support\AgentReporting;
use App\Support\MalaysianBanks;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public function dashboard(AgentReporting $report, AgentCommissions $commissions)
    {
        $agent = Auth::guard('agent')->user();

        return view('agents.dashboard', [
            'agent' => $agent, 'summary' => $report->summary($agent),
            'earnings' => $commissions->earnings($agent),
            'rate' => $agent->effectiveCommissionRate(),
            'leads' => $report->leads($agent)->with(['quote.booking', 'quote.invoice', 'quotes.booking', 'quotes.invoice'])->latest()->limit(5)->get(),
            'media' => AgentMedia::available()->latest()->limit(3)->get(), 'report' => $report,
        ]);
    }

    public function leads(AgentReporting $report)
    {
        return view('agents.leads', [
            'leads' => $report->leads(Auth::guard('agent')->user())->with(['quote.booking', 'quote.invoice', 'quotes.booking', 'quotes.invoice'])->latest()->paginate(20),
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
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:40'], 'folder' => ['nullable', 'integer']]);
        $category = $filters['category'] ?? null;
        $folders = AgentMediaFolder::orderBy('position')->orderBy('name')->get()->keyBy('id');
        $folder = isset($filters['folder']) ? $folders->get($filters['folder']) : null;
        abort_if(isset($filters['folder']) && ! $folder, 404);

        // A folder is shown only when it (or a subfolder) holds content agents can see.
        $visible = [];
        foreach (AgentMedia::available()->whereNotNull('folder_id')->distinct()->pluck('folder_id') as $id) {
            for ($guard = 0; $id && $guard < 10 && ! isset($visible[$id]); $guard++) {
                $visible[$id] = true;
                $id = $folders->get($id)?->parent_id;
            }
        }
        $subfolders = $category ? collect() : $folders->filter(fn ($f) => $f->parent_id === $folder?->id && isset($visible[$f->id]))->values();

        // Category filters search the whole library; otherwise show the open folder's own items.
        $media = AgentMedia::available()
            ->when($category, fn ($q) => $q->where('category', $category), fn ($q) => $q->where('folder_id', $folder?->id))
            ->latest()->paginate(24)->withQueryString();

        $agent = Auth::guard('agent')->user();

        return view('agents.media', [
            'media' => $media, 'folder' => $folder, 'subfolders' => $subfolders, 'category' => $category,
            'trail' => $folder?->trail() ?? collect(),
            'counts' => AgentMedia::available()->whereIn('folder_id', $subfolders->pluck('id'))->selectRaw('folder_id, COUNT(*) AS total')->groupBy('folder_id')->pluck('total', 'folder_id'),
            'referralUrl' => $agent->referralSource?->tracking_url,
            'publicUrl' => rtrim(config('agents.public_url'), '/'),
            'pages' => ['/' => 'Home page', ...collect(config('cleaning-services'))->mapWithKeys(fn ($s, $slug) => ['/services/'.$slug => $s['name']])->all()],
        ]);
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
        abort_unless(AgentMedia::available()->whereKey($media->id)->exists() && ($media->isImage() || $media->isVideo()), 404);
        abort_unless($media->path && Storage::disk('local')->exists($media->path), 404);

        // BinaryFileResponse honours Range requests, which Safari/iOS need to play video.
        return response()->file(Storage::disk('local')->path($media->path), [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Public, signed and expiring: still only serves content HQ has published and not expired. */
    public function shared(AgentMedia $media)
    {
        abort_unless(AgentMedia::available()->whereKey($media->id)->exists() && ($media->isImage() || $media->isVideo()), 404);
        abort_unless($media->path && Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path), [
            'Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="'.addcslashes($media->original_name ?? 'cucinow', '"\\').'"',
        ]);
    }

    public function account()
    {
        return view('agents.account', ['agent' => Auth::guard('agent')->user()]);
    }

    public function updatePhoto(Request $request)
    {
        $request->validateWithBag('photo', ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000']]);
        $agent = Auth::guard('agent')->user();
        $path = $request->file('photo')->store('agent-photos', 'local');
        abort_unless($path, 500, 'The photo could not be stored.');
        if ($agent->profile_photo_path) {
            Storage::disk('local')->delete($agent->profile_photo_path);
        }
        $agent->forceFill(['profile_photo_path' => $path])->save();
        AgentActivity::create(['agent_id' => $agent->id, 'action' => 'profile.photo_updated']);

        return back()->with('success', 'Profile photo updated.');
    }

    public function deletePhoto()
    {
        $agent = Auth::guard('agent')->user();
        if ($agent->profile_photo_path) {
            Storage::disk('local')->delete($agent->profile_photo_path);
            $agent->forceFill(['profile_photo_path' => null])->save();
        }

        return back()->with('success', 'Profile photo removed.');
    }

    /** The signed-in agent's own photo; HQ uses its own route. Files stay private. */
    public function photo()
    {
        $path = Auth::guard('agent')->user()->profile_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=600']);
    }

    public function update(Request $request)
    {
        // The form shows a fixed +60 prefix, so the agent types e.g. "12-345 6789"; a pasted +60 / 0 is fine too.
        $request->merge(['phone_local' => ltrim(preg_replace('/^60/', '', preg_replace('/\D+/', '', (string) $request->input('phone_local'))), '0')]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone_local' => ['required', 'regex:/^1\d{7,9}$|^[3-9]\d{7,8}$/'],
            'location' => ['required', 'string', 'max:120'],
            'social_links' => ['nullable', 'array:'.implode(',', array_keys(Agent::SOCIALS))],
            'social_links.*' => ['nullable', 'string', 'max:200'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ], ['phone_local.regex' => 'Enter a Malaysian number, e.g. 12-345 6789.']);

        $socials = [];
        foreach (Agent::SOCIALS as $key => [$label]) {
            $url = Agent::socialUrl($key, $data['social_links'][$key] ?? null);
            if ($url !== null && (! filter_var($url, FILTER_VALIDATE_URL) || ($key !== 'website' && ! preg_match('#/@?[\w.\-]{1,100}$#u', $url)))) {
                throw ValidationException::withMessages(["social_links.$key" => "Check your {$label} handle."]);
            }
            $socials[$key] = $url;
        }

        $agent = Auth::guard('agent')->user();
        if (! empty($data['password']) && ! Hash::check($data['current_password'], $agent->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }
        $agent->fill([
            'name' => $data['name'], 'location' => $data['location'],
            'phone' => '+60'.$data['phone_local'],
            'social_links' => array_filter($socials) ?: null,
            ...(empty($data['password']) ? [] : ['password' => $data['password']]),
        ])->save();
        AgentActivity::create(['agent_id' => $agent->id, 'action' => 'profile.updated']);

        return back()->with('success', 'Your profile has been updated.');
    }

    /** Bank account HQ pays commission into. Needs the current password: a hijacked session must not redirect payouts. */
    public function updatePayout(Request $request)
    {
        $request->merge([
            'payout_account_number' => preg_replace('/[\s-]+/', '', (string) $request->input('payout_account_number')),
            'payout_account_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('payout_account_name'))),
        ]);
        $data = $request->validateWithBag('payout', [
            'payout_bank' => ['required', Rule::in(array_keys(MalaysianBanks::LIST))],
            'payout_account_name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\\pL\\pM\\s.'@\\/&(),-]+$/u"],
            'payout_account_number' => ['required', 'regex:/^\d{6,20}$/'],
            'payout_password' => ['required', 'string'],
        ], [
            'payout_bank.required' => 'Choose your bank.',
            'payout_account_name.regex' => 'Use the account holder name exactly as it appears on your bank account.',
            'payout_account_number.regex' => 'Enter 6–20 digits only (spaces and dashes are removed automatically).',
            'payout_password.required' => 'Enter your current password to save bank details.',
        ]);
        $agent = Auth::guard('agent')->user();
        if (! Hash::check($data['payout_password'], $agent->password)) {
            return back()->withErrors(['payout_password' => 'Your current password is incorrect.'], 'payout')->withInput($request->except('payout_password'));
        }

        $agent->forceFill([
            'payout_bank' => $data['payout_bank'],
            'payout_account_name' => mb_strtoupper($data['payout_account_name']),
            'payout_account_number' => $data['payout_account_number'],
            'payout_details_updated_at' => now(),
        ])->save();
        // Never log the full number; the last four digits are enough to spot a change.
        AgentActivity::create(['agent_id' => $agent->id, 'action' => 'payout.updated', 'details' => ['bank' => $data['payout_bank'], 'account_last4' => substr($data['payout_account_number'], -4)]]);

        return back()->with('success', 'Your bank details have been saved. HQ will pay approved commission into this account.');
    }
}
