<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Run\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Impex\Domains\Run\Data\RunStats;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;

/**
 * Run stats were computed.
 */
final class RunStatsShownActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RunStats $stats,
        public ?Model $viewer = null,
    ) {}
}
