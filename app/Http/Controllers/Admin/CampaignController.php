<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterCampaign;
use App\Mail\NewsletterPreviewMail;
use App\Models\NewsletterCampaign;
use App\Support\NewsletterHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

    public function store(Request $request, NewsletterHtmlSanitizer $sanitizer): RedirectResponse
    {
        $request->merge([
            'action' => $request->input('action', 'draft'),
            'content' => $sanitizer->sanitize($request->string('content')->toString()),
        ]);

        $data = $request->validate([
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

    public function send(NewsletterCampaign $campaign): RedirectResponse
    {
        if (! in_array($campaign->status, ['draft', 'scheduled', 'failed'], true)) {
            return back()->with('error', 'This campaign has already been queued or sent.');
        }

        $campaign->update(['status' => 'queued', 'delivery_error' => null]);
        SendNewsletterCampaign::dispatch($campaign->id);

        return back()->with('success', 'Newsletter campaign queued for delivery.');
    }
}
