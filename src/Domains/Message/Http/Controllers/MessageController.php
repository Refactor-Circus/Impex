<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Message\Http\Requests\IndexMessagesRequest;
use JayI\Impex\Domains\Message\Http\Requests\ShowMessageRequest;
use JayI\Impex\Domains\Message\Models\MessageModel;

final class MessageController
{
    public function index(IndexMessagesRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function show(ShowMessageRequest $request, MessageModel $message): JsonResponse
    {
        return $request->persist();
    }
}
