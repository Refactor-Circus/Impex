<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Impex\Domains\Channel\Models\ChannelModel;

/**
 * A tool call about one stored channel, named by `channel`.
 */
abstract class ChannelMcpRequest extends Request
{
    protected function channel(): ChannelModel
    {
        /** @var string $name */
        $name = $this->get('channel');

        return ChannelModel::query()->where('name', $name)->firstOrFail();
    }
}
