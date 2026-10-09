<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Contracts;

use Illuminate\Http\Request;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;

/**
 * Decides whether an inbound request really came from the sender it claims.
 *
 * Every upstream signs differently, so this is a real extension point: one
 * implementation ships, others are written by the host application.
 */
interface SignatureValidator
{
    public function isValid(Request $request, ChannelConfig $config): bool;
}
