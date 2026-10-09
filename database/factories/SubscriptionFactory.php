<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * @extends Factory<SubscriptionModel>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = SubscriptionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscriber_id' => SubscriberModel::factory(),
            'stream' => 'example',
            'topics' => 0xFFFFFFFF >> 1,
            'selection' => Selection::All,
            'format' => 'slice',
            'status' => SubscriptionStatus::Active,
        ];
    }
}
