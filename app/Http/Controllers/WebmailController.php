<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class WebmailController extends Controller
{
    public function __invoke(): View
    {
        $webmailUrl = trim((string) config('webmail.login_url'));
        $scheme = strtolower((string) parse_url($webmailUrl, PHP_URL_SCHEME));
        $webmailHost = (string) parse_url($webmailUrl, PHP_URL_HOST);

        abort_unless(
            filter_var($webmailUrl, FILTER_VALIDATE_URL)
                && $scheme === 'https'
                && $webmailHost !== '',
            503,
            'Webmail is temporarily unavailable.'
        );

        return view('webmail', compact('webmailUrl', 'webmailHost'));
    }
}
