<?php

namespace App\Http\Controllers;

use App\Mail\SubscriberWelcomeMail;
use App\Models\Subscriber;
use App\Support\SubscriberSegments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:160'], 'name' => ['nullable', 'string', 'max:120']]);

        $subscriber = Subscriber::updateOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'] ?? null, 'status' => 'subscribed', 'source' => 'website', 'subscribed_at' => now(), 'unsubscribed_at' => null],
        );

        Mail::to($subscriber->email, $subscriber->name)
            ->queue(new SubscriberWelcomeMail($subscriber));

        return back()->with('newsletter_success', 'Thank you. Cleaning tips and service updates are on the way.');
    }

    public function index(Request $request, SubscriberSegments $subscriberSegments): View
    {
        $subscribers = $this->filteredQuery($request, $subscriberSegments)
            ->latest()
            ->paginate(30)
            ->withQueryString();
        $segments = $subscriberSegments->all();

        return view('admin.subscribers.index', compact('subscribers', 'segments'));
    }

    public function adminStore(Request $request, SubscriberSegments $subscriberSegments): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('subscribers', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['subscribed', 'unsubscribed'])],
            'segment' => ['nullable', 'string', 'max:50', 'not_regex:/[|;,]/'],
        ]);
        $segments = $subscriberSegments->normalize($data['segment'] ?? null);

        if (($data['segment'] ?? '') !== '' && $segments === []) {
            return back()->withInput()->with('error', 'Enter a valid segment name.');
        }

        $subscriber = Subscriber::create([
            'name' => Str::limit(Str::squish(strip_tags((string) ($data['name'] ?? ''))), 120, '') ?: null,
            'email' => $data['email'],
            'phone' => Str::limit(Str::squish(strip_tags((string) ($data['phone'] ?? ''))), 30, '') ?: null,
            'status' => $data['status'],
            'source' => 'manual',
            'segments' => $segments,
            'subscribed_at' => now(),
            'unsubscribed_at' => $data['status'] === 'unsubscribed' ? now() : null,
        ]);

        return back()->with('success', "Subscriber {$subscriber->email} added.");
    }

    public function import(Request $request, SubscriberSegments $subscriberSegments): RedirectResponse
    {
        $data = $request->validate([
            'subscribers_csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);
        $handle = fopen($data['subscribers_csv']->getRealPath(), 'rb');

        if ($handle === false) {
            return back()->with('error', 'The CSV file could not be opened.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return back()->with('error', 'The CSV file is empty.');
        }

        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);
        $rawHeaders = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = array_map(fn (?string $header): string => $this->normalizeCsvHeader((string) $header), $rawHeaders);

        if (! in_array('email', $headers, true)) {
            fclose($handle);

            return back()->with('error', 'The CSV must contain an email column.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $processed = 0;

        DB::transaction(function () use ($handle, $delimiter, $headers, $subscriberSegments, &$created, &$updated, &$skipped, &$processed): void {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (++$processed > 5000) {
                    $skipped++;

                    continue;
                }

                $row = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
                $values = array_combine($headers, $row);
                $email = Str::lower(trim((string) ($values['email'] ?? '')));
                $statusValue = Str::lower(trim((string) ($values['status'] ?? '')));

                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
                    $skipped++;

                    continue;
                }

                if ($statusValue !== '' && ! in_array($statusValue, ['subscribed', 'unsubscribed'], true)) {
                    $skipped++;

                    continue;
                }

                $subscriber = Subscriber::firstOrNew(['email' => $email]);
                $isNew = ! $subscriber->exists;
                $incomingSegments = $subscriberSegments->normalize($values['segments'] ?? '');
                $subscriber->segments = $subscriberSegments->normalize([
                    ...($subscriber->segments ?? []),
                    ...$incomingSegments,
                ]);

                if (array_key_exists('name', $values)) {
                    $subscriber->name = Str::limit(Str::squish(strip_tags((string) $values['name'])), 120, '') ?: null;
                }
                if (array_key_exists('phone', $values)) {
                    $subscriber->phone = Str::limit(Str::squish(strip_tags((string) $values['phone'])), 30, '') ?: null;
                }

                $subscriber->status = $statusValue !== '' ? $statusValue : ($subscriber->status ?: 'subscribed');
                $subscriber->source = $subscriber->source ?: 'csv_import';
                $subscriber->subscribed_at ??= now();
                $subscriber->unsubscribed_at = $subscriber->status === 'unsubscribed'
                    ? ($subscriber->unsubscribed_at ?? now())
                    : null;
                $subscriber->save();

                $isNew ? $created++ : $updated++;
            }
        });
        fclose($handle);

        $message = "CSV import completed: {$created} created, {$updated} updated";
        if ($skipped > 0) {
            $message .= ", {$skipped} skipped";
        }

        return back()->with('success', $message.'.');
    }

    public function export(Request $request, SubscriberSegments $subscriberSegments): StreamedResponse
    {
        $query = $this->filteredQuery($request, $subscriberSegments);
        $filename = 'cucinow-subscribers-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['email', 'name', 'phone', 'status', 'source', 'segments', 'subscribed_at', 'unsubscribed_at']);

            $query->orderBy('id')->chunkById(500, function ($subscribers) use ($output): void {
                foreach ($subscribers as $subscriber) {
                    fputcsv($output, [
                        $this->safeCsvValue($subscriber->email),
                        $this->safeCsvValue($subscriber->name),
                        $this->safeCsvValue($subscriber->phone),
                        $subscriber->status,
                        $subscriber->source,
                        implode('|', $subscriber->segments ?? []),
                        $subscriber->subscribed_at?->toDateTimeString(),
                        $subscriber->unsubscribed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateSegments(Request $request, SubscriberSegments $subscriberSegments): RedirectResponse
    {
        $data = $request->validate([
            'subscriber_ids' => ['required', 'array', 'min:1', 'max:500'],
            'subscriber_ids.*' => ['required', 'integer', 'distinct', Rule::exists('subscribers', 'id')],
            'segment_action' => ['required', Rule::in(['add', 'remove'])],
            'segment' => ['required', 'string', 'max:50', 'not_regex:/[|;,]/'],
        ]);
        $segment = $subscriberSegments->normalize($data['segment'])[0] ?? null;

        if ($segment === null) {
            return back()->with('error', 'Enter a valid segment name.');
        }

        $subscribers = Subscriber::whereKey($data['subscriber_ids'])->get();
        DB::transaction(function () use ($subscribers, $subscriberSegments, $segment, $data): void {
            foreach ($subscribers as $subscriber) {
                $segments = $subscriberSegments->normalize($subscriber->segments);

                if ($data['segment_action'] === 'add') {
                    $segments = $subscriberSegments->normalize([...$segments, $segment]);
                } else {
                    $segments = array_values(array_filter(
                        $segments,
                        fn (string $value): bool => ! Str::of($value)->lower()->exactly(Str::lower($segment)),
                    ));
                }

                $subscriber->update(['segments' => $segments]);
            }
        });

        return back()->with('success', ucfirst($data['segment_action'])." segment ‘{$segment}’ for {$subscribers->count()} subscriber(s).");
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $email = $subscriber->email;
        $subscriber->delete();

        return back()->with('success', "Subscriber {$email} deleted.");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subscriber_ids' => ['required', 'array', 'min:1', 'max:500'],
            'subscriber_ids.*' => ['required', 'integer', 'distinct', Rule::exists('subscribers', 'id')],
        ]);
        $deleted = Subscriber::whereKey($data['subscriber_ids'])->delete();

        return back()->with('success', "Deleted {$deleted} subscriber(s).");
    }

    public function unsubscribe(Request $request, Subscriber $subscriber): View|Response
    {
        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        if ($request->isMethod('post')) {
            return response('', 200);
        }

        return view('newsletter-unsubscribed', compact('subscriber'));
    }

    private function filteredQuery(Request $request, SubscriberSegments $subscriberSegments): Builder
    {
        $query = Subscriber::query()
            ->when($request->string('q')->trim()->toString(), function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('email', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when(
                in_array($request->string('status')->toString(), ['subscribed', 'unsubscribed', 'bounced'], true),
                fn (Builder $query): Builder => $query->where('status', $request->string('status')->toString()),
            );

        return $subscriberSegments->constrain($query, [$request->string('segment')->toString()]);
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];

        return (string) array_search(max($counts), $counts, true);
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = (string) Str::of($header)
            ->replace("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');

        return match ($header) {
            'full_name', 'subscriber_name' => 'name',
            'mobile', 'mobile_number', 'phone_number' => 'phone',
            'segment', 'group', 'groups' => 'segments',
            default => $header,
        };
    }

    private function safeCsvValue(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
