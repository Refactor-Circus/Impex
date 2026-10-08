<?php

declare(strict_types=1);

namespace JayI\Impex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JayI\Impex\Domains\Subscription\Enums\SubscriberStatus;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

/**
 * @extends Factory<SubscriberModel>
 */
final class SubscriberFactory extends Factory
{
    protected $model = SubscriberModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'client_id' => (string) $this->faker->unique()->uuid(),
            'status' => SubscriberStatus::Active,
        ];
    }
}
