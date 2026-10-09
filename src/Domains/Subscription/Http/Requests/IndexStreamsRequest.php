<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListStreamsAction;

final class IndexStreamsRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return ListStreamsAction::rules();
    }

    public function persist(): JsonResponse
    {
        return new JsonResponse(['data' => app(ListStreamsAction::class)->execute()]);
    }

    protected function allowsOperator(): bool
    {
        return true;
    }
}
