<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Data;

/**
 * What came of one send: whether the other side took it, and the ledger row
 * that records it.
 */
final readonly class Receipt
{
    /**
     * @param  array<string, mixed>|null  $error
     */
    public function __construct(
        public bool $successful,
        public string $messageId,
        public ?int $statusCode = null,
        public ?int $durationMs = null,
        public ?array $error = null,
        public ?string $response = null,
    ) {}

    public function failed(): bool
    {
        return ! $this->successful;
    }
}
