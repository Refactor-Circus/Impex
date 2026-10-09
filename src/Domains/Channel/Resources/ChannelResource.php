<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;

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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->describe();
    }
}
