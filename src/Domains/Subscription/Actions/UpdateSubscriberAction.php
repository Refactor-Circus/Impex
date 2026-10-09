<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriberStatus;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberUpdatedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriberUpdatingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;

final class UpdateSubscriberAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'client_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'status' => ['sometimes', Rule::enum(SubscriberStatus::class)],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SubscriberModel $subscriber, array $data): SubscriberModel
    {
        SubscriberUpdatingActionEvent::dispatch($subscriber, $data);

        $subscriber->fill(array_intersect_key($data, array_flip(['name', 'client_id', 'status', 'metadata'])))->save();

        SubscriberUpdatedActionEvent::dispatch($subscriber);

        return $subscriber;
    }
}
