<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\UpdateSubscriptionSubjectsAction;

final class UpdateSubscriptionSubjectsRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return UpdateSubscriptionSubjectsAction::rules();
    }

    public function persist(): JsonResponse
    {
        return new JsonResponse(['data' => app(UpdateSubscriptionSubjectsAction::class)->execute($this->subscription(), $this->validated())]);
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('update', $this->subscription());
    }
}
