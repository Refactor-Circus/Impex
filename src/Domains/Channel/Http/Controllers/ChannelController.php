<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Channel\Http\Requests\DestroyChannelRequest;
use RefactorCircus\Impex\Domains\Channel\Http\Requests\IndexChannelsRequest;
use RefactorCircus\Impex\Domains\Channel\Http\Requests\RotateChannelSecretRequest;
use RefactorCircus\Impex\Domains\Channel\Http\Requests\ShowChannelRequest;
use RefactorCircus\Impex\Domains\Channel\Http\Requests\StoreChannelRequest;
use RefactorCircus\Impex\Domains\Channel\Http\Requests\UpdateChannelRequest;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;

final class ChannelController
{
    public function index(IndexChannelsRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function show(ShowChannelRequest $request, string $name): JsonResponse
    {
        return $request->persist();
    }

    public function store(StoreChannelRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function update(UpdateChannelRequest $request, ChannelModel $channel): JsonResponse
    {
        return $request->persist();
    }

    public function destroy(DestroyChannelRequest $request, ChannelModel $channel): JsonResponse
    {
        return $request->persist();
    }

    public function rotateSecret(RotateChannelSecretRequest $request, ChannelModel $channel): JsonResponse
    {
        return $request->persist();
    }
}
