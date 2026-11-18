<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\View\View;

class PublicQuoteController extends Controller
{
    public function __invoke(Quote $quote): View
    {
        abort_if($quote->status === 'draft', 404);

        if ($quote->status === 'sent') {
            $quote->update(['status' => 'viewed', 'viewed_at' => $quote->viewed_at ?? now()]);
        }

        $quote->load(['customer', 'items.service']);

        return view('emails.quote', [
            'quote' => $quote,
            'publicMode' => true,
        ]);
    }
}
