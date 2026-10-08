<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Subscription\Actions\ShowSubscriptionAction;
use JayI\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class ShowSubscriptionRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return ShowSubscriptionAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new SubscriptionResource(app(ShowSubscriptionAction::class)->execute($this->subscription())))->response();
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('view', $this->subscription());
    }
}
