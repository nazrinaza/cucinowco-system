<?php

namespace App\Http\Controllers\Admin;

use App\Actions\PurgeTestCustomer;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::with(['siteVisitRequests.referralSource'])->withCount(['quotes', 'bookings', 'invoices'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($nested) => $nested->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Customer $customer, PurgeTestCustomer $purger): View
    {
        $customer->load(['siteVisitRequests.referralSource', 'quotes' => fn ($q) => $q->with('siteVisit.referralSource')->latest(), 'bookings' => fn ($q) => $q->latest(), 'invoices' => fn ($q) => $q->latest()]);
        $cleanupSummary = $purger->summary($customer);

        return view('admin.customers.show', compact('customer', 'cleanupSummary'));
    }

    public function destroyTestData(Request $request, Customer $customer, PurgeTestCustomer $purger): RedirectResponse
    {
        $confirmation = "DELETE CUSTOMER {$customer->id}";
        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$confirmation])],
            'confirm_test_data' => ['accepted'],
        ], [
            'confirmation.in' => "Type {$confirmation} exactly to continue.",
            'confirm_test_data.accepted' => 'Confirm that this customer and all linked records are test data.',
        ]);

        $summary = $purger->handle($customer);

        return redirect()->route('admin.customers.index')->with(
            'success',
            "Test customer removed with {$summary['site_visits']} site visit(s), {$summary['quotes']} quotation(s), {$summary['invoices']} invoice(s) and RM ".number_format((float) $summary['amount_paid'], 2).' paid revenue.',
        );
    }
}
