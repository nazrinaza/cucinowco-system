<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterCampaign;
use App\Mail\NewsletterPreviewMail;
use App\Models\NewsletterCampaign;
use App\Support\NewsletterHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = NewsletterCampaign::latest()->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    public function edit(NewsletterCampaign $campaign): View|RedirectResponse
    {
        if (! $this->isEditable($campaign)) {
            return redirect()->route('admin.campaigns.index')
                ->with('error', 'Queued and sent campaigns cannot be changed. Duplicate this campaign to reuse its content.');
        }

        $campaigns = NewsletterCampaign::latest()->paginate(20);

        return view('admin.campaigns.index', [
            'campaigns' => $campaigns,
            'editingCampaign' => $campaign,
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
            'alt' => ['nullable', 'string', 'max:180'],
        ]);

        $path = $data['image']->store('newsletters/'.now()->format('Y/m'), 'public');

        if (! $path) {
            abort(500, 'The newsletter image could not be stored.');
        }

        $publicUrl = Storage::disk('public')->url($path);

        return response()->json([
            'url' => str_starts_with($publicUrl, 'http://') || str_starts_with($publicUrl, 'https://')
                ? $publicUrl
                : url($publicUrl),
            'alt' => trim($data['alt'] ?? ''),
        ]);
    }

    public function store(Request $request, NewsletterHtmlSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->validateCampaign($request, $sanitizer);

        $action = $data['action'];
        unset($data['action']);

        if ($action === 'test') {
            $campaign = new NewsletterCampaign([...$data, 'status' => 'draft']);
            $recipient = $request->user()->email;

            try {
                Mail::to($recipient, $request->user()->name)
                    ->send(new NewsletterPreviewMail($campaign, $recipient));
            } catch (Throwable $exception) {
                report($exception);

                return back()->withInput()->with('error', 'The test email could not be sent. Check the Resend configuration and Laravel log.');
            }

            return back()->withInput()->with('success', "Test email sent immediately to {$recipient}.");
        }

        if ($action === 'send') {
            $campaign = NewsletterCampaign::create([...$data, 'scheduled_at' => null, 'status' => 'queued']);
            SendNewsletterCampaign::dispatch($campaign->id);

            return back()->with('success', 'Campaign saved and queued for immediate delivery.');
        }

        NewsletterCampaign::create([...$data, 'status' => ! empty($data['scheduled_at']) ? 'scheduled' : 'draft']);

        return back()->with('success', ! empty($data['scheduled_at']) ? 'Campaign scheduled.' : 'Campaign saved as a draft.');
    }

    public function update(
        Request $request,
        NewsletterCampaign $campaign,
        NewsletterHtmlSanitizer $sanitizer,
    ): RedirectResponse {
        if (! $this->isEditable($campaign)) {
            return redirect()->route('admin.campaigns.index')
                ->with('error', 'Queued and sent campaigns cannot be changed. Duplicate this campaign to reuse its content.');
        }

        $data = $this->validateCampaign($request, $sanitizer);
        $action = $data['action'];
        unset($data['action']);

        if ($action === 'test') {
            $preview = new NewsletterCampaign([...$data, 'status' => 'draft']);
            $recipient = $request->user()->email;

            try {
                Mail::to($recipient, $request->user()->name)
                    ->send(new NewsletterPreviewMail($preview, $recipient));
            } catch (Throwable $exception) {
                report($exception);

                return back()->withInput()->with('error', 'The test email could not be sent. Check the Resend configuration and Laravel log.');
            }

            return back()->withInput()->with('success', "Test email sent immediately to {$recipient}. Your saved campaign has not been changed.");
        }

        if ($action === 'send') {
            $campaign->update([
                ...$data,
                'scheduled_at' => null,
                'status' => 'queued',
                'delivery_error' => null,
            ]);
            SendNewsletterCampaign::dispatch($campaign->id);

            return redirect()->route('admin.campaigns.index')
                ->with('success', 'Campaign updated and queued for immediate delivery.');
        }

        $campaign->update([
            ...$data,
            'status' => ! empty($data['scheduled_at']) ? 'scheduled' : 'draft',
            'delivery_error' => null,
        ]);

        return redirect()->route('admin.campaigns.edit', $campaign)
            ->with('success', ! empty($data['scheduled_at']) ? 'Campaign updated and scheduled.' : 'Campaign draft updated.');
    }

    public function duplicate(NewsletterCampaign $campaign): RedirectResponse
    {
        $copy = NewsletterCampaign::create([
            'name' => Str::limit('Copy of '.$campaign->name, 150, ''),
            'subject' => $campaign->subject,
            'preview_text' => $campaign->preview_text,
            'content' => $campaign->content,
            'segments' => $campaign->segments,
            'status' => 'draft',
        ]);

        return redirect()->route('admin.campaigns.edit', $copy)
            ->with('success', 'Campaign copied as a new draft. You can edit and reuse it safely.');
    }

    public function send(NewsletterCampaign $campaign): RedirectResponse
    {
        if (! in_array($campaign->status, ['draft', 'scheduled', 'failed'], true)) {
            return back()->with('error', 'This campaign has already been queued or sent.');
        }

        $campaign->update(['status' => 'queued', 'delivery_error' => null]);
        SendNewsletterCampaign::dispatch($campaign->id);

        return back()->with('success', 'Newsletter campaign queued for delivery.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCampaign(Request $request, NewsletterHtmlSanitizer $sanitizer): array
    {
        $request->merge([
            'action' => $request->input('action', 'draft'),
            'content' => $sanitizer->sanitize($request->string('content')->toString()),
        ]);

        return $request->validate([
            'action' => ['required', Rule::in(['draft', 'send', 'test'])],
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:180'],
            'preview_text' => ['nullable', 'string', 'max:220'],
            'content' => [
                'required',
                'string',
                'max:30000',
                function (string $attribute, mixed $value, \Closure $fail) use ($sanitizer): void {
                    if (! $sanitizer->hasMeaningfulContent((string) $value)) {
                        $fail('The newsletter message must contain readable text.');
                    }
                },
            ],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);
    }

    private function isEditable(NewsletterCampaign $campaign): bool
    {
        return in_array($campaign->status, ['draft', 'scheduled', 'failed'], true);
    }
}
