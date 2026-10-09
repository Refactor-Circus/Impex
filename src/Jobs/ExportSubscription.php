<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Subscription\Services\Exporter;
use RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware;

/**
 * Writes a subscription's full export to the export disk.
 */
final class ExportSubscription implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesConfiguredMiddleware;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $subscriptionId) {}

    public function handle(Exporter $exporter): void
    {
        $exporter->export($this->subscriptionId);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['impex', 'subscription:'.$this->subscriptionId];
    }
}
