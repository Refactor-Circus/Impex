<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriberData;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberUpdatedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberUpdatingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

final class UpdateSubscriberAction
{
    public function execute(SubscriberModel $subscriber, UpdateSubscriberData $data): SubscriberModel
    {
        SubscriberUpdatingActionEvent::dispatch($subscriber, $data);

        DB::transaction(fn (): bool => $subscriber->fill($data->toArray())->save());

        SubscriberUpdatedActionEvent::dispatch($subscriber);

        return $subscriber;
    }
}
