<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Subscription\Actions\ListSubscriptionEventsAction;

final class IndexSubscriptionEventsRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return ListSubscriptionEventsAction::rules();
    }

    public function persist(): JsonResponse
    {
        $page = app(ListSubscriptionEventsAction::class)->execute($this->subscription(), $this->validated());

        return new JsonResponse(['data' => $page['events'], 'cursor' => $page['cursor'], 'has_more' => $page['has_more']]);
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('view', $this->subscription());
    }
}
