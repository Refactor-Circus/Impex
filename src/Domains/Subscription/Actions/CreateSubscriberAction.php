<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Subscription\Data\CreateSubscriberData;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberCreatedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberCreatingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

final class CreateSubscriberAction
{
    /**
     * @param  Model|null  $owner  Who manages the subscriber, such as the
     *                             vendor record it stands for.
     */
    public function execute(CreateSubscriberData $data, ?Model $owner = null): SubscriberModel
    {
        SubscriberCreatingActionEvent::dispatch($data, $owner);

        $subscriber = DB::transaction(fn (): SubscriberModel => SubscriberModel::query()->create([
            'name' => $data->name,
            'client_id' => $data->clientId,
            'status' => $data->status,
            'metadata' => $data->metadata,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner === null ? null : (string) $owner->getKey(),
        ]));

        SubscriberCreatedActionEvent::dispatch($subscriber);

        return $subscriber;
    }
}
