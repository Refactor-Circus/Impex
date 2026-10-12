<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscriptionsAction;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionsData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class ListSubscriptionsMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', SubscriptionModel::class);
    }

    protected function rules(): array
    {
        return ListSubscriptionsData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $subscriptions = app(ListSubscriptionsAction::class)->execute(ListSubscriptionsData::fromValidated($validated), null, $this->actor());

        return SubscriptionResource::collection($subscriptions)->toStructuredResponse();
    }
}
