<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class ClientIpResolver
{
    public function resolve(Request $request): ?string
    {
        $remoteAddress = trim((string) $request->server('REMOTE_ADDR', ''));
        if (! $this->isValidIp($remoteAddress)) {
            return null;
        }

        $cloudflareRanges = config('cloudflare.proxy_ranges', []);
        if (! IpUtils::checkIp($remoteAddress, $cloudflareRanges)) {
            // Never trust a visitor-supplied CF-Connecting-IP header on a direct request.
            return $remoteAddress;
        }

        $connectingIp = trim((string) $request->header('CF-Connecting-IP', ''));
        if (! $this->isValidIp($connectingIp) || IpUtils::checkIp($connectingIp, $cloudflareRanges)) {
            // Avoid recording the Cloudflare edge address when its verified client header is unusable.
            return null;
        }

        return $connectingIp;
    }

    private function isValidIp(string $value): bool
    {
        return $value !== '' && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
}
