<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = NewsletterCampaign::latest()->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'subject' => ['required', 'string', 'max:180'], 'preview_text' => ['nullable', 'string', 'max:220'], 'content' => ['required', 'string', 'max:30000'], 'scheduled_at' => ['nullable', 'date', 'after:now']]);
        NewsletterCampaign::create([...$data, 'status' => ! empty($data['scheduled_at']) ? 'scheduled' : 'draft']);

        return back()->with('success', 'Campaign saved.');
    }
}
