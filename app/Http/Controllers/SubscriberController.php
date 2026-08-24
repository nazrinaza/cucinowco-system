<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriberController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:160'], 'name' => ['nullable', 'string', 'max:120']]);

        Subscriber::updateOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'] ?? null, 'status' => 'subscribed', 'source' => 'website', 'subscribed_at' => now(), 'unsubscribed_at' => null],
        );

        return back()->with('newsletter_success', 'Thank you. Cleaning tips and service updates are on the way.');
    }

    public function index(Request $request): View
    {
        $subscribers = Subscriber::query()->when($request->string('q')->toString(), fn ($q, $term) => $q->where('email', 'like', "%{$term}%"))->latest()->paginate(20)->withQueryString();

        return view('admin.subscribers.index', compact('subscribers'));
    }
}
