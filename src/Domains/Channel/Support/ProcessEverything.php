<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Support;

use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Channel\Contracts\ChannelProfile;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

/**
 * Turns every authenticated request into a run.
 */
final class ProcessEverything implements ChannelProfile
{
    public function shouldProcess(Request $request, ChannelConfig $config): bool
    {
        return true;
    }
}
