<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteVisitRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteVisitController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'scheduled', 'completed', 'cancelled'];

    public function index(Request $request): View
    {
        $siteVisits = SiteVisitRequest::with(['customer', 'service', 'quote'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where(fn ($nested) => $nested
                ->where('reference_number', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.site-visits.index', ['siteVisits' => $siteVisits, 'statuses' => self::STATUSES]);
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
}
