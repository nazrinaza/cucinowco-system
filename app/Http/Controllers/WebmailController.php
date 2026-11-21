<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class WebmailController extends Controller
{
    public function __invoke(): Response
    {
        $webmailUrl = trim((string) config('webmail.login_url'));
        $destinationPath = trim((string) config('webmail.destination_path'));
        $scheme = strtolower((string) parse_url($webmailUrl, PHP_URL_SCHEME));
        $webmailHost = (string) parse_url($webmailUrl, PHP_URL_HOST);
        $port = parse_url($webmailUrl, PHP_URL_PORT);

        abort_unless(
            filter_var($webmailUrl, FILTER_VALIDATE_URL)
                && $scheme === 'https'
                && $webmailHost !== '',
            503,
            'Webmail is temporarily unavailable.'
        );

        abort_unless(
            str_starts_with($destinationPath, '/')
                && ! str_starts_with($destinationPath, '//')
                && ! preg_match('/[\r\n]/', $destinationPath),
            503,
            'Webmail is temporarily unavailable.'
        );

        $webmailOrigin = 'https://'.$webmailHost.($port ? ':'.$port : '');
        $loginAction = $webmailOrigin.'/login/';

        return response()
            ->view('webmail', compact('destinationPath', 'loginAction', 'webmailHost'))
            ->header('Cache-Control', 'no-store, private')
            ->header('Content-Security-Policy', "default-src 'self'; base-uri 'none'; form-action {$webmailOrigin}; frame-ancestors 'none'; img-src 'self' data:; style-src 'self'; script-src 'none'; connect-src 'none'; object-src 'none'")
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY');
    }
}
