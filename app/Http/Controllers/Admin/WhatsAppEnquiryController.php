<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppEnquiry;
use Illuminate\Http\Request;

class WhatsAppEnquiryController extends Controller
{
    public function index(Request $request)
    {
        $enquiries = WhatsAppEnquiry::query()
            ->when($request->filled('status'), fn ($query) => $query->where('provider_status', $request->string('status')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.whatsapp-enquiries.index', compact('enquiries'));
    }
}
