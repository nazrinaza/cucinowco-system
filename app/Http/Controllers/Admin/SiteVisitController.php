<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteVisitRequest;
use App\Models\SiteVisitPhoto;
use App\Support\SitePhotoStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteVisitController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'scheduled', 'completed', 'cancelled'];

    private const STATUS_COLOURS = [
        'new' => ['background' => '#f5b800', 'text' => '#25282d'],
        'contacted' => ['background' => '#3d7ea6', 'text' => '#ffffff'],
        'scheduled' => ['background' => '#405b7f', 'text' => '#ffffff'],
        'completed' => ['background' => '#2b8a62', 'text' => '#ffffff'],
        'cancelled' => ['background' => '#c84538', 'text' => '#ffffff'],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $siteVisits = $this->filteredQuery($filters)
            ->with(['customer', 'service', 'quote'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.site-visits.index', ['siteVisits' => $siteVisits, 'statuses' => self::STATUSES]);
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $rangeStart = CarbonImmutable::parse($filters['start'])->toDateString();
        $rangeEnd = CarbonImmutable::parse($filters['end'])->toDateString();

        $events = $this->filteredQuery($filters)
            ->with(['customer:id,name,company_name', 'service:id,name'])
            ->whereNotNull('preferred_date')
            ->whereDate('preferred_date', '>=', $rangeStart)
            ->whereDate('preferred_date', '<', $rangeEnd)
            ->orderBy('preferred_date')
            ->get()
            ->map(function (SiteVisitRequest $siteVisit): array {
                $status = array_key_exists($siteVisit->status, self::STATUS_COLOURS) ? $siteVisit->status : 'new';
                $colours = self::STATUS_COLOURS[$status];
                $timeSlot = str($siteVisit->preferred_time_slot ?: 'Time flexible')->replace('_', ' ')->title();

                return [
                    'id' => (string) $siteVisit->id,
                    'title' => "{$timeSlot} · {$siteVisit->customer->name}",
                    'start' => $siteVisit->preferred_date->format('Y-m-d'),
                    'allDay' => true,
                    'url' => route('admin.site-visits.show', $siteVisit),
                    'color' => $colours['background'],
                    'contrastColor' => $colours['text'],
                    'className' => "cucinow-calendar-event site-visit-event-{$status}",
                    'extendedProps' => [
                        'reference' => $siteVisit->reference_number,
                        'status' => ucfirst($siteVisit->status),
                        'service' => $siteVisit->clean_types_label,
                        'company' => $siteVisit->customer->company_name,
                        'address' => $siteVisit->site_address,
                    ],
                ];
            })
            ->values();

        return response()->json($events);
    }

    public function show(SiteVisitRequest $siteVisit): View
    {
        $siteVisit->load(['customer', 'service', 'quote.booking', 'photos.uploadedBy', 'photos.beforeReference']);

        return view('admin.site-visits.show', ['siteVisit' => $siteVisit, 'statuses' => self::STATUSES]);
    }

    public function update(Request $request, SiteVisitRequest $siteVisit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $updates = $data;

        if ($data['status'] === 'contacted' && ! $siteVisit->contacted_at) {
            $updates['contacted_at'] = now();
        }

        if ($data['status'] === 'completed' && ! $siteVisit->completed_at) {
            $updates['completed_at'] = now();
        }

        $siteVisit->update($updates);

        return back()->with('success', 'Site visit request updated.');
    }

    public function uploadPhoto(Request $request, SiteVisitRequest $siteVisit, SitePhotoStorage $photoStorage): RedirectResponse
    {
        $data = $request->validate([
            'phase' => ['required', Rule::in(['before', 'after'])],
            'photo' => ['required', 'file', 'max:'.SitePhotoStorage::MAX_UPLOAD_KB,
                function (string $attribute, mixed $value, \Closure $fail) use ($photoStorage): void {
                    if ($value instanceof \Illuminate\Http\UploadedFile && ! $photoStorage->detectedMime($value)) {
                        $fail('Choose a valid JPG, PNG, WebP, HEIC or HEIF image.');
                    }
                }],
            'caption' => ['required_if:phase,before', 'nullable', 'string', 'max:180'],
            'before_photo_id' => ['required_if:phase,after', 'nullable', 'integer',
                Rule::exists('site_visit_photos', 'id')
                    ->where('site_visit_request_id', $siteVisit->id)
                    ->where('phase', 'before')],
        ]);

        if ($siteVisit->photos()->where('phase', $data['phase'])->count() >= 30) {
            return back()->withErrors(['photo' => 'This gallery is limited to 30 photos for each stage.']);
        }

        if ($data['phase'] === 'after' && ! $siteVisit->photos()->where('phase', 'before')->exists()) {
            return back()->withErrors(['photo' => 'Upload at least one before photo first.']);
        }

        $file = $data['photo'];
        $stored = $photoStorage->store($file, $siteVisit->id, $data['phase']);

        try {
            $siteVisit->photos()->create([
                'phase' => $data['phase'],
                'before_photo_id' => $data['phase'] === 'after' ? $data['before_photo_id'] : null,
                'path' => $stored['path'],
                'mime_type' => $stored['mime_type'],
                'original_name' => $file->getClientOriginalName(),
                'caption' => $data['caption'] ?? null,
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored['path']);
            throw $exception;
        }

        return back()->with('success', ucfirst($data['phase']).' photo uploaded.');
    }

    public function pairPhoto(Request $request, SiteVisitRequest $siteVisit, SiteVisitPhoto $photo): RedirectResponse
    {
        abort_unless($photo->site_visit_request_id === $siteVisit->id && $photo->phase === 'after', 404);
        $data = $request->validate([
            'before_photo_id' => ['required', 'integer', Rule::exists('site_visit_photos', 'id')
                ->where('site_visit_request_id', $siteVisit->id)->where('phase', 'before')],
        ]);
        $photo->update(['before_photo_id' => $data['before_photo_id']]);

        return back()->with('success', 'After photo paired with its before angle.');
    }

    public function photo(SiteVisitRequest $siteVisit, SiteVisitPhoto $photo): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($photo->site_visit_request_id === $siteVisit->id, 404);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        $extension = match ($photo->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            default => 'bin',
        };

        return Storage::disk('local')->response($photo->path, "site-photo-{$photo->id}.{$extension}", [
            'Content-Type' => $photo->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function deletePhoto(SiteVisitRequest $siteVisit, SiteVisitPhoto $photo): RedirectResponse
    {
        abort_unless($photo->site_visit_request_id === $siteVisit->id, 404);
        if ($photo->phase === 'before' && $photo->afterPhotos()->exists()) {
            return back()->withErrors(['photo' => 'This angle has paired after photos. Reassign or remove those photos first.']);
        }
        if ($siteVisit->quote?->booking?->status === 'completed'
            && $siteVisit->photos()->where('phase', $photo->phase)->count() <= 1) {
            return back()->withErrors(['photo' => 'Upload a replacement first. Completed bookings must retain before and after evidence.']);
        }
        Storage::disk('local')->delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }

    /**
     * @param  array{status?: string|null, q?: string|null}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return SiteVisitRequest::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $nested) => $nested
                ->where('reference_number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))));
    }
}
