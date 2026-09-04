<?php

namespace App\Http\Controllers;

use App\Mail\SubscriberWelcomeMail;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

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

    public function index(Request $request): View
    {
        $subscribers = Subscriber::query()->when($request->string('q')->toString(), fn ($q, $term) => $q->where('email', 'like', "%{$term}%"))->latest()->paginate(20)->withQueryString();

        return view('admin.subscribers.index', compact('subscribers'));
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
}
