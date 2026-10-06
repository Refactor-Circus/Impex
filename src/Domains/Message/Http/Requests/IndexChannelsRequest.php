<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Message\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Message\Actions\ListChannelsAction;

final class IndexChannelsRequest extends Request
{
    public function rules(): array
    {
        return ListChannelsAction::rules();
    }

    public function persist(): JsonResponse
    {
        return new JsonResponse(['data' => app(ListChannelsAction::class)->execute()]);
    }
}
