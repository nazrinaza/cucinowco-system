<?php

namespace Tests\Unit;

use App\Support\ClientIpResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientIpResolverTest extends TestCase
{
    public function test_it_uses_cloudflare_connecting_ip_only_for_a_verified_cloudflare_proxy(): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '173.245.48.10']);
        $request->headers->set('CF-Connecting-IP', '203.0.113.24');

        $this->assertSame('203.0.113.24', app(ClientIpResolver::class)->resolve($request));
    }

    public function test_it_ignores_a_spoofed_cloudflare_header_on_a_direct_request(): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.42']);
        $request->headers->set('CF-Connecting-IP', '203.0.113.99');

        $this->assertSame('198.51.100.42', app(ClientIpResolver::class)->resolve($request));
    }

    #[DataProvider('unsafeCloudflareRequests')]
    public function test_it_never_stores_a_cloudflare_edge_ip_when_the_client_header_is_unusable(?string $header): void
    {
        $request = Request::create('/', server: ['REMOTE_ADDR' => '2606:4700::1234']);
        if ($header !== null) {
            $request->headers->set('CF-Connecting-IP', $header);
        }

        $this->assertNull(app(ClientIpResolver::class)->resolve($request));
    }

    public static function unsafeCloudflareRequests(): array
    {
        return [
            'missing header' => [null],
            'invalid header' => ['not-an-ip'],
            'another Cloudflare address' => ['104.16.10.20'],
        ];
    }
}
