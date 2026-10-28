<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralSource;
use App\Models\SiteVisitPhoto;
use App\Models\SiteVisitRequest;
use App\Models\Staff;
use App\Support\SitePhotoStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        $siteVisits = $this->filteredQuery($filters)
            ->with(['customer', 'service', 'quote', 'referralSource', 'calendarSync'])
            ->withCount('quotes')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.site-visits.index', [
            'siteVisits' => $siteVisits,
            'statuses' => self::STATUSES,
            'referralSources' => ReferralSource::orderBy('name')->get(['name', 'code']),
        ]);
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'q' => ['nullable', 'string', 'max:150'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        $rangeStart = CarbonImmutable::parse($filters['start'])->toDateString();
        $rangeEnd = CarbonImmutable::parse($filters['end'])->toDateString();

        $events = $this->filteredQuery($filters)
            ->with(['customer:id,name,company_name', 'service:id,name', 'referralSource:id,name,channel'])
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
                        'source' => $siteVisit->referral_label,
                    ],
                ];
            })
            ->values();

        return response()->json($events);
    }

    public function show(SiteVisitRequest $siteVisit): View
    {
        $siteVisit->load(['customer', 'service', 'staff', 'calendarSync', 'assessmentCompletedBy', 'quotes' => fn ($query) => $query->with(['invoice', 'booking'])->latest(), 'referralSource', 'photos.uploadedBy']);

        return view('admin.site-visits.show', [
            'siteVisit' => $siteVisit,
            'statuses' => self::STATUSES,
            'staff' => Staff::where('status', '!=', 'inactive')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SiteVisitRequest $siteVisit): RedirectResponse
    {
        $requiresOtherCleanType = in_array('others', $siteVisit->clean_types ?? [], true);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'staff_id' => ['nullable', 'exists:staff,id'],
            'scheduled_start' => [Rule::requiredIf(fn (): bool => in_array($request->input('status'), ['scheduled', 'completed'], true)), 'nullable', 'date'],
            'scheduled_end' => [Rule::requiredIf(fn (): bool => in_array($request->input('status'), ['scheduled', 'completed'], true)), 'nullable', 'date', 'after:scheduled_start'],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
            'other_clean_type' => $requiresOtherCleanType
                ? ['required', 'string', 'max:180']
                : ['nullable', 'string', 'max:180'],
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
            'photo' => ['required', 'file', 'max:'.SitePhotoStorage::MAX_UPLOAD_KB,
                function (string $attribute, mixed $value, \Closure $fail) use ($photoStorage): void {
                    if ($value instanceof UploadedFile && ! $photoStorage->detectedMime($value)) {
                        $fail('Choose a valid JPG, PNG, WebP, HEIC or HEIF image.');
                    }
                }],
            'caption' => ['required', 'string', 'max:180'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        if ($siteVisit->photos()->count() >= 60) {
            return back()->withErrors(['photo' => 'This site-visit gallery is limited to 60 photos.']);
        }

        $file = $data['photo'];
        $stored = $photoStorage->store($file, $siteVisit->id, 'inspection');

        try {
            $siteVisit->photos()->create([
                'phase' => 'inspection',
                'before_photo_id' => null,
                'path' => $stored['path'],
                'mime_type' => $stored['mime_type'],
                'original_name' => $file->getClientOriginalName(),
                'caption' => $data['caption'] ?? null,
                'notes' => $data['notes'],
                'uploaded_by_user_id' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($stored['path']);
            throw $exception;
        }

        return back()->with('success', 'Site-visit photo uploaded.');
    }

    public function updateAssessment(Request $request, SiteVisitRequest $siteVisit): RedirectResponse
    {
        $completing = $request->input('assessment_state') === 'completed';
        $data = $request->validate([
            'assessment_state' => ['required', Rule::in(['draft', 'completed'])],
            'discovery_source' => [Rule::requiredIf($completing), 'nullable', Rule::in(array_keys(config('site_visits.discovery_sources')))],
            'discovery_source_other' => ['nullable', 'required_if:discovery_source,other', 'string', 'max:180'],
            'site_size_sqft' => [Rule::requiredIf($completing), 'nullable', 'integer', 'min:1', 'max:100000000'],
            'floor_count' => [Rule::requiredIf($completing), 'nullable', 'integer', 'min:1', 'max:500'],
            'premises_type' => [Rule::requiredIf($completing), 'nullable', Rule::in(array_keys(config('site_visits.premises_types')))],
            'premises_type_other' => ['nullable', 'required_if:premises_type,other', 'string', 'max:180'],
            'tools' => ['nullable', 'array'],
            'tools.*' => [Rule::in(array_keys(config('site_visits.tools')))],
            'other_tools' => ['nullable', 'string', 'max:1000'],
            'ppe' => ['nullable', 'array'],
            'ppe.*' => [Rule::in(array_keys(config('site_visits.ppe')))],
            'other_ppe' => ['nullable', 'string', 'max:1000'],
            'safety_signage_required' => ['nullable', 'boolean'],
            'forklift_required' => ['nullable', 'boolean'],
            'expected_duration_hours' => [Rule::requiredIf($completing), 'nullable', 'numeric', 'decimal:0,2', 'min:0.25', 'max:10000'],
            'access_notes' => ['nullable', 'string', 'max:2000'],
            'assessment_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $siteVisit->update([
            'assessment_data' => [
                'discovery_source' => $data['discovery_source'] ?? null,
                'discovery_source_other' => $data['discovery_source_other'] ?? null,
                'site_size_sqft' => isset($data['site_size_sqft']) ? (int) $data['site_size_sqft'] : null,
                'floor_count' => isset($data['floor_count']) ? (int) $data['floor_count'] : null,
                'premises_type' => $data['premises_type'] ?? null,
                'premises_type_other' => $data['premises_type_other'] ?? null,
                'tools' => array_values($data['tools'] ?? []),
                'other_tools' => $data['other_tools'] ?? null,
                'ppe' => array_values($data['ppe'] ?? []),
                'other_ppe' => $data['other_ppe'] ?? null,
                'safety_signage_required' => $request->boolean('safety_signage_required'),
                'forklift_required' => $request->boolean('forklift_required'),
                'expected_duration_hours' => isset($data['expected_duration_hours']) ? (float) $data['expected_duration_hours'] : null,
                'access_notes' => $data['access_notes'] ?? null,
                'assessment_notes' => $data['assessment_notes'] ?? null,
            ],
            ...($completing ? [
                'assessment_completed_at' => now(),
                'assessment_completed_by_user_id' => $request->user()->id,
            ] : []),
        ]);

        return back()->with('success', $completing ? 'Site assessment completed.' : 'Site assessment draft saved.');
    }

    public function photo(SiteVisitRequest $siteVisit, SiteVisitPhoto $photo): StreamedResponse
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
        $photo->afterPhotos()->update(['before_photo_id' => null]);
        Storage::disk('local')->delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }

    /**
     * @param  array{status?: string|null, q?: string|null, source?: string|null}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return SiteVisitRequest::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, function (Builder $query, string $source): void {
                $source === 'direct'
                    ? $query->whereNull('referral_source_id')
                    : $query->where('referral_code', $source);
            })
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $nested) => $nested
                ->where('reference_number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer) => $customer
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))));
    }
}
