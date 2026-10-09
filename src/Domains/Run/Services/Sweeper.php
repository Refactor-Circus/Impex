<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Services;

use RefactorCircus\Impex\Domains\Batch\Services\BatchRunner;
use RefactorCircus\Impex\Domains\Run\Data\SweepReport;
use RefactorCircus\Impex\Domains\Signal\Services\Waits;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionSweeper;

/**
 * The engine's periodic pass.
 *
 * Five jobs, each of which the rest of the engine deliberately leaves undone
 * because it cannot be done in-process:
 *
 * - fire timers, because the queue's delay ceiling is far shorter than the
 *   waits a flow can express
 * - reclaim leases, because a killed invocation cannot release its own
 * - enforce deadlines, because a step inside an upstream call cannot check a
 *   clock
 * - finalise batches whose completion check lost its throttle lock
 * - queue subscription detection and delivery a lost job never ran, and
 *   deliveries whose backoff has elapsed
 *
 * Exposed as a class rather than living in the command so it can be called from
 * a job, a test, or a health check.
 */
final class Sweeper
{
    public function __construct(
        private readonly Waits $waits,
        private readonly BatchRunner $batches,
        private readonly SubscriptionSweeper $subscriptions,
    ) {}

    /**
     * Run every pass, reporting what each one did.
     */
    public function sweep(?int $limit = null): SweepReport
    {
        $deadlines = $this->waits->enforceDeadlines();
        $subscriptions = $this->subscriptions->sweep();

        return new SweepReport(
            timers: $this->waits->sweep($limit),
            leases: $this->waits->reclaimLeases() + $this->batches->reclaimLeases(),
            batches: $this->batches->sweep(),
            expiredSteps: $deadlines['steps'],
            expiredRuns: $deadlines['runs'],
            detections: $subscriptions['detections'],
            deliveries: $subscriptions['deliveries'],
        );
    }
}
