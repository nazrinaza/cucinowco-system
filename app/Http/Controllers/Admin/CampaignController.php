<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterCampaign;
use App\Mail\NewsletterPreviewMail;
use App\Models\EmailEvent;
use App\Models\NewsletterCampaign;
use App\Support\NewsletterHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function analytics(NewsletterCampaign $campaign): View
    {
        $events = EmailEvent::query()
            ->where('provider', 'resend')
            ->where('metadata->campaign_id', (string) $campaign->id)
            ->orderBy('occurred_at')
            ->get();

        $unique = fn (array $types): int => $this->uniqueEventCount($events, $types);
        $recipientCount = (int) $campaign->recipient_count;
        $deliveredCount = $unique(['email.delivered']);
        $uniqueOpenCount = $unique(['email.opened']);
        $uniqueClickCount = $unique(['email.clicked']);
        $issueCount = $unique(['email.bounced', 'email.failed', 'email.suppressed', 'email.complained']);
        $rateBase = max($recipientCount, $deliveredCount, 1);

        $metrics = [
            'recipients' => $recipientCount,
            'delivered' => $deliveredCount,
            'unique_opens' => $uniqueOpenCount,
            'total_opens' => $events->where('event_type', 'email.opened')->count(),
            'unique_clicks' => $uniqueClickCount,
            'total_clicks' => $events->where('event_type', 'email.clicked')->count(),
            'bounced' => $unique(['email.bounced']),
            'failed' => $unique(['email.failed']),
            'blocked' => $unique(['email.suppressed']),
            'complained' => $unique(['email.complained']),
            'delayed' => $unique(['email.delivery_delayed']),
            'issues' => $issueCount,
            'open_rate' => round(($uniqueOpenCount / $rateBase) * 100, 1),
            'click_rate' => round(($uniqueClickCount / $rateBase) * 100, 1),
            'delivery_rate' => round(($deliveredCount / $rateBase) * 100, 1),
            'issue_rate' => round(($issueCount / $rateBase) * 100, 1),
        ];

        $timelineStart = ($campaign->sent_at ?? $events->first()?->occurred_at ?? $campaign->created_at ?? now())
            ->copy()
            ->startOfDay();
        $timeline = collect(range(0, 13))->map(function (int $offset) use ($events, $timelineStart): array {
            $date = $timelineStart->copy()->addDays($offset);
            $dayEvents = $events->filter(fn (EmailEvent $event): bool => $event->occurred_at?->isSameDay($date) ?? false);

            return [
                'date' => $date,
                'opens' => $dayEvents->where('event_type', 'email.opened')->count(),
                'clicks' => $dayEvents->where('event_type', 'email.clicked')->count(),
                'issues' => $dayEvents->whereIn('event_type', ['email.bounced', 'email.failed', 'email.suppressed', 'email.complained'])->count(),
            ];
        });
        $timelineMaximum = max(1, (int) $timeline->max(fn (array $day): int => $day['opens'] + $day['clicks'] + $day['issues']));

        $problemLabels = [
            'email.bounced' => 'Bounced',
            'email.failed' => 'Failed',
            'email.suppressed' => 'Blocked',
            'email.complained' => 'Spam complaint',
            'email.delivery_delayed' => 'Delayed',
        ];
        $problemEvents = $events
            ->whereIn('event_type', array_keys($problemLabels))
            ->sortByDesc('occurred_at')
            ->take(20);

        return view('admin.campaigns.analytics', compact(
            'campaign',
            'events',
            'metrics',
            'timeline',
            'timelineMaximum',
            'problemEvents',
            'problemLabels',
        ));
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

    /**
     * @param  Collection<int, EmailEvent>  $events
     * @param  array<int, string>  $types
     */
    private function uniqueEventCount(Collection $events, array $types): int
    {
        return $events
            ->whereIn('event_type', $types)
            ->unique(fn (EmailEvent $event): string => $event->provider_email_id
                ?: $event->recipient
                ?: 'event-'.$event->id)
            ->count();
    }
}
