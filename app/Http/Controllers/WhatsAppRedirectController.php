<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsAppRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $phone = preg_replace('/\D/', '', (string) config('company.whatsapp'));
        $message = mb_substr((string) $request->query('text', ''), 0, 500);
        $destination = "https://wa.me/{$phone}";

        if ($message !== '') {
            $destination .= '?text='.rawurlencode($message);
        }

        return redirect()->away($destination);
    }
}
