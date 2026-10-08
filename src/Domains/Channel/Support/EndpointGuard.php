<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Support;

use JayI\Impex\Domains\Channel\Exceptions\UnsafeEndpointException;

/**
 * Keeps an endpoint someone outside the application supplied from reaching
 * inside it.
 *
 * A subscriber registers a URL and the application calls it; without this, a
 * URL resolving to 169.254.169.254 or 10.0.0.5 turns every delivery into a
 * request from inside the network (SSRF). The host is resolved once, here,
 * and the request is pinned to the address that passed, so a DNS answer that
 * changes between the check and the call cannot slip past it.
 */
final class EndpointGuard
{
    /**
     * The URL's host and the address to pin it to, for curl's
     * CURLOPT_RESOLVE: `host:port:address`.
     *
     * @throws UnsafeEndpointException
     */
    public function resolve(string $url, bool $allowInsecure = false): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if ($scheme !== 'https' && ! ($allowInsecure && $scheme === 'http')) {
            throw UnsafeEndpointException::scheme($url);
        }

        if ($host === '') {
            throw UnsafeEndpointException::unresolvable($url);
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $address = $this->address(trim($host, '[]'), $url);

        return sprintf('%s:%d:%s', $host, $port, $address);
    }

    private function address(string $host, string $url): string
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            throw UnsafeEndpointException::unresolvable($url);
        }

        foreach ($addresses as $address) {
            $public = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($public === false) {
                throw UnsafeEndpointException::privateAddress($url, $address);
            }
        }

        return $addresses[0];
    }
}
