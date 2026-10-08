<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use JayI\Impex\Domains\Subscription\Enums\SubscriberStatus;
use JayI\Impex\Domains\Subscription\Events\SubscriberCreatedActionEvent;
use JayI\Impex\Domains\Subscription\Events\SubscriberCreatingActionEvent;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

final class CreateSubscriberAction
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // The OAuth client the subscriber's systems authenticate as.
            'client_id' => ['sometimes', 'nullable', 'string', 'max:191', Rule::unique('impex_subscribers', 'client_id')],
            'status' => ['sometimes', Rule::enum(SubscriberStatus::class)],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Model|null  $owner  Who manages the subscriber, such as the
     *                             vendor record it stands for.
     */
    public function execute(array $data, ?Model $owner = null): SubscriberModel
    {
        SubscriberCreatingActionEvent::dispatch($data, $owner);

        $subscriber = SubscriberModel::query()->create([
            'name' => $data['name'],
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? SubscriberStatus::Active,
            'metadata' => $data['metadata'] ?? null,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner === null ? null : (string) $owner->getKey(),
        ]);

        SubscriberCreatedActionEvent::dispatch($subscriber);

        return $subscriber;
    }
}
