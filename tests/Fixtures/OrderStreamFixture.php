<?php

declare(strict_types=1);

namespace JayI\Impex\Tests\Fixtures;

use JayI\Impex\Domains\Subscription\Enums\StreamKind;
use JayI\Impex\Domains\Subscription\Support\AbstractStream;

/**
 * An append stream: orders placed and shipped, each delivered as it is.
 */
final class OrderStreamFixture extends AbstractStream
{
    public function key(): string
    {
        return 'fixture.orders';
    }

    public function kind(): StreamKind
    {
        return StreamKind::Append;
    }

    public function topics(): array
    {
        return ['orders', 'shipping'];
    }
}
