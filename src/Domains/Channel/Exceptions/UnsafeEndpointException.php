<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Exceptions;

use JayI\Impex\Exceptions\ImpexException;

final class UnsafeEndpointException extends ImpexException
{
    protected int $status = 422;

    public static function scheme(string $url): self
    {
        return new self(sprintf('The endpoint [%s] must use https.', $url));
    }

    public static function unresolvable(string $url): self
    {
        return new self(sprintf('The endpoint [%s] does not resolve to an address. Check the host name.', $url));
    }

    public static function privateAddress(string $url, string $address): self
    {
        return new self(sprintf(
            'The endpoint [%s] resolves to [%s], a private or reserved address. Use a publicly reachable host.',
            $url,
            $address,
        ));
    }
}
