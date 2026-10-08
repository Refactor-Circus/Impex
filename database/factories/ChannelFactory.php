<?php

declare(strict_types=1);

namespace JayI\Impex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JayI\Impex\Domains\Channel\Enums\BodyPolicy;
use JayI\Impex\Domains\Channel\Enums\ChannelStatus;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Message\Enums\Direction;

/**
 * @extends Factory<ChannelModel>
 */
final class ChannelFactory extends Factory
{
    protected $model = ChannelModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'channel-'.$this->faker->unique()->slug(2),
            'direction' => Direction::Outbound,
            'transport' => 'http',
            'status' => ChannelStatus::Active,
            'options' => ['url' => 'https://example.test/webhook'],
            'credentials' => null,
            'body_policy' => BodyPolicy::All,
        ];
    }
}
