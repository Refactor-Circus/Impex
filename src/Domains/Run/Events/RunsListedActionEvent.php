<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Run\Events;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Impex\Domains\Run\Models\RunModel;

/**
 * Runs were listed.
 */
final class RunsListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  CursorPaginator<int, RunModel>  $runs
     */
    public function __construct(
        public CursorPaginator $runs,
        public ?Model $viewer = null,
    ) {}
}
