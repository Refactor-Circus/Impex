<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscriptionEventsAction;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionEventsData;

final class IndexSubscriptionEventsRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return ListSubscriptionEventsData::rules();
    }

    public function persist(): JsonResponse
    {
        $page = app(ListSubscriptionEventsAction::class)->execute($this->subscription(), ListSubscriptionEventsData::fromValidated($this->validated()));

        return new JsonResponse(['data' => $page['events'], 'cursor' => $page['cursor'], 'has_more' => $page['has_more']]);
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('view', $this->subscription());
    }
}
