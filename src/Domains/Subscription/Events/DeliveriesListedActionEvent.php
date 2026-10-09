<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Events;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\DeliveryModel;

/**
 * A subscription's deliveries were listed.
 */
final class DeliveriesListedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  CursorPaginator<int, DeliveryModel>  $deliveries
     */
    public function __construct(
        public CursorPaginator $deliveries,
    ) {}
}
