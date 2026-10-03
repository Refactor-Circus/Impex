<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Support;

use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Signal\Models\SignalModel;
use JayI\Impex\Impex;

/**
 * A run you can act on, rather than one you have to look things up about.
 */
final class RunHandle
{
    public function __construct(public readonly RunModel $run) {}

    public function id(): string
    {
        return (string) $this->run->getKey();
    }

    public function signal(string $name, mixed $payload = null, ?string $idempotencyKey = null): SignalModel
    {
        return app(Impex::class)->signal($this->run, $name, $payload, $idempotencyKey);
    }

    /**
     * Deliver a signal, treating a finished run as a no-op.
     */
    public function signalIfRunning(string $name, mixed $payload = null, ?string $idempotencyKey = null): bool
    {
        return app(Impex::class)->signalIfRunning($this->run, $name, $payload, $idempotencyKey);
    }

    public function cancel(?string $reason = null): RunModel
    {
        return app(Impex::class)->cancel($this->run, $reason);
    }

    public function retry(): RunModel
    {
        return app(Impex::class)->retry($this->run);
    }

    public function result(): mixed
    {
        return app(Impex::class)->result($this->run->refresh());
    }

    public function refresh(): RunModel
    {
        return $this->run->refresh();
    }
}
