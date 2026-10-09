<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Support\Policies\Policy;

/**
 * A stored channel belongs to whoever created it: a subscriber manages their
 * own endpoint and nobody else's. Channels with no owner are the
 * application's, readable by anyone signed in and changed only by operators —
 * through the route middleware with authorization off, or in config.
 */
class ChannelPolicy extends Policy
{
    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user, ChannelModel $channel): bool
    {
        return $channel->owner_type === null || $channel->isOwnedBy($user);
    }

    public function create(Model $user): bool
    {
        return true;
    }

    public function update(Model $user, ChannelModel $channel): bool
    {
        return $channel->isOwnedBy($user);
    }

    public function delete(Model $user, ChannelModel $channel): bool
    {
        return $channel->isOwnedBy($user);
    }
}
