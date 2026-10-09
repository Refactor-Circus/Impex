<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Services;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Config\Repository as Config;
use RefactorCircus\Impex\Jobs\DeliverSubscription;
use RefactorCircus\Impex\Jobs\DetectStream;
use RefactorCircus\Impex\Jobs\ExportSubscription;

/**
 * Queues subscription work on the queues `impex.subscriptions.queue` names.
 *
 * Detection and delivery sit on separate queues so a backlog of one cannot
 * starve the other: a ten-million-product import should not hold up the
 * order webhooks queued behind it.
 */
final class SubscriptionJobs
{
    public function __construct(
        private readonly Bus $bus,
        private readonly Config $config,
    ) {}

    /**
     * One detection job per shard. Shards share the work through leases, so
     * more of them only means more of it done at once.
     */
    public function detect(string $stream): void
    {
        /** @var int $shards */
        $shards = $this->config->get('impex.subscriptions.detection.concurrency', 1);

        for ($shard = 0; $shard < max(1, $shards); $shard++) {
            $this->bus->dispatch($this->route(new DetectStream($stream, $shard), 'detect')->afterCommit());
        }
    }

    public function deliver(string $subscriptionId): void
    {
        $this->bus->dispatch($this->route(new DeliverSubscription($subscriptionId), 'deliver')->afterCommit());
    }

    public function export(string $subscriptionId): void
    {
        $this->bus->dispatch($this->route(new ExportSubscription($subscriptionId), 'deliver')->afterCommit());
    }

    /**
     * @template TJob of DetectStream|DeliverSubscription|ExportSubscription
     *
     * @param  TJob  $job
     * @return TJob
     */
    private function route(DetectStream|DeliverSubscription|ExportSubscription $job, string $lane): DetectStream|DeliverSubscription|ExportSubscription
    {
        $connection = $this->config->get("impex.subscriptions.queue.{$lane}.connection")
            ?? $this->config->get('impex.queue.connection');
        $queue = $this->config->get("impex.subscriptions.queue.{$lane}.queue")
            ?? $this->config->get('impex.queue.queue');

        if (is_string($connection)) {
            $job->onConnection($connection);
        }

        if (is_string($queue)) {
            $job->onQueue($queue);
        }

        return $job;
    }
}
