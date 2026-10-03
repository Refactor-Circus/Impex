<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Support;

use Illuminate\Http\Request;
use JayI\Impex\Domains\Message\Contracts\ChannelProfile;
use JayI\Impex\Domains\Message\Data\ChannelConfig;

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
