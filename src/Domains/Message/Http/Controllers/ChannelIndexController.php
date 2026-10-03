<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Message\Http\Requests\IndexChannelsRequest;

final class ChannelIndexController
{
    public function __invoke(IndexChannelsRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
