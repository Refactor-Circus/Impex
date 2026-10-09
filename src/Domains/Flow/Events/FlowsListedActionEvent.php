<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Flow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;

/**
 * The registered flows were listed.
 */
final class FlowsListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $flows
     */
    public function __construct(
        public array $flows,
    ) {}
}
