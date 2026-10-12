<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Enums\ChannelStatus;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * A channel, configured or stored. Secrets are never part of it.
 *
 * @property ChannelConfig $resource
 */
final class ChannelResource extends JsonResource
{
    public function __construct(ChannelConfig|ChannelModel $channel)
    {
        parent::__construct($channel instanceof ChannelModel ? ChannelConfig::fromModel($channel) : $channel);
    }

    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->nullable()->description('Null for a configured channel.')->required(),
            'name' => $schema->string()->required(),
            'direction' => $schema->string()->enum(array_column(Direction::cases(), 'value'))->required(),
            'transport' => $schema->string()->required(),
            'status' => $schema->string()->enum(array_column(ChannelStatus::cases(), 'value'))->required(),
            'body_policy' => $schema->string()->enum(array_column(BodyPolicy::cases(), 'value'))->required(),
            'verifies_signatures' => $schema->boolean()->required(),
            'flow' => $schema->string()->nullable()->description('The flow an inbound channel starts.')->required(),
            'path' => $schema->string()->nullable()->required(),
            'options' => $schema->object()->description('Transport settings.')->required(),
            'stored' => $schema->boolean()->description('False for a channel from config.')->required(),
            'owner_type' => $schema->string()->nullable()->required(),
            'owner_id' => $schema->string()->nullable()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->describe();
    }
}
