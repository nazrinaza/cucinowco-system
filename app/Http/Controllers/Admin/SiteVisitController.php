<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteVisitRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                        'service' => $siteVisit->service?->name ?? 'Service review required',
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
        $siteVisit->load(['customer', 'service', 'quote']);

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
