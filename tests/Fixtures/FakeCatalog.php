<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Tests\Fixtures;

/**
 * An in-memory catalogue the fixture product stream reads, with a count of
 * how often it was asked, so tests can prove loading is batched.
 */
final class FakeCatalog
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public static array $products = [];

    public static int $loads = 0;

    public static function reset(): void
    {
        self::$products = [];
        self::$loads = 0;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function put(string $sku, array $product): void
    {
        self::$products[$sku] = $product;
    }
}
