<?php

declare(strict_types=1);

namespace JayI\Impex\Console\Commands;

use Illuminate\Console\Command;
use JayI\Impex\Domains\Artifact\Models\ArtifactModel;
use JayI\Impex\Domains\Message\Models\MessageModel;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Subscription\Models\DeliveryModel;
use JayI\Impex\Domains\Subscription\Services\EventPruner;

/**
 * Prunes Impex history in dependency order.
 *
 * Runs and messages first, then artifacts — an artifact must never be removed
 * while a row still points at it, or a failed run loses the payloads you would
 * open it to read.
 */
final class PruneCommand extends Command
{
    protected $signature = 'impex:prune';

    protected $description = 'Prune expired Impex runs, messages, subscription events, deliveries, and artifacts';

    public function handle(EventPruner $events): int
    {
        $runs = (new RunModel)->pruneAll();
        $messages = (new MessageModel)->pruneAll();
        $subscriptionEvents = $events->prune();
        $deliveries = (new DeliveryModel)->pruneAll();
        $artifacts = (new ArtifactModel)->pruneAll();

        $this->components->info(sprintf(
            'Pruned %d run(s), %d message(s), %d subscription event(s), %d deliveries, and %d artifact(s).',
            $runs,
            $messages,
            $subscriptionEvents,
            $deliveries,
            $artifacts,
        ));

        return self::SUCCESS;
    }
}
