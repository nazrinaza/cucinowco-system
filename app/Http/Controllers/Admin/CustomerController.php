<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::withCount(['quotes', 'bookings', 'invoices'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($nested) => $nested->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['quotes' => fn ($q) => $q->latest(), 'bookings' => fn ($q) => $q->latest(), 'invoices' => fn ($q) => $q->latest()]);

        return view('admin.customers.show', compact('customer'));
    }
}
