<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ServicePageController extends Controller
{
    public function show(string $service): View
    {
        $services = config('cleaning-services');
        abort_unless(isset($services[$service]), 404);

        $details = $services[$service];
        $related = collect($details['related'])
            ->mapWithKeys(fn (string $slug) => [$slug => $services[$slug]])
            ->all();

        return view('services.show', compact('service', 'details', 'related'));
    }
}
