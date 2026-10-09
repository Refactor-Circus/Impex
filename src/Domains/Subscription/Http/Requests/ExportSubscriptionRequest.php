<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\ExportSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class ExportSubscriptionRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return ExportSubscriptionAction::rules();
    }

    public function persist(): JsonResponse
    {
        $subscription = app(ExportSubscriptionAction::class)->execute($this->subscription());

        return (new SubscriptionResource($subscription))->response()->setStatusCode(202);
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('update', $this->subscription());
    }
}
