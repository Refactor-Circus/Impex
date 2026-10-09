<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;

/**
 * @extends Factory<MessageModel>
 */
final class MessageFactory extends Factory
{
    protected $model = MessageModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direction' => Direction::Inbound,
            'channel' => 'example',
            'transport' => 'http',
            'endpoint' => 'https://example.test/webhook',
            'method' => 'POST',
            'bytes' => 0,
            'occurred_at' => Carbon::now(),
        ];
    }
}
