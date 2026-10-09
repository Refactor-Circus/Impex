<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Message\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Message\Http\Requests\IndexMessagesRequest;
use RefactorCircus\Impex\Domains\Message\Http\Requests\ShowMessageRequest;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;

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
