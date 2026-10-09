<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Mcp\Requests;

use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Keystone\Mcp\Requests\Request;

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
