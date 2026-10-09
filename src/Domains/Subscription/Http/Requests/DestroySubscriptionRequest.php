<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\DeleteSubscriptionAction;

final class DestroySubscriptionRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return DeleteSubscriptionAction::rules();
    }

    public function persist(): JsonResponse
    {
        app(DeleteSubscriptionAction::class)->execute($this->subscription());

        return new JsonResponse(null, 204);
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('delete', $this->subscription());
    }
}
