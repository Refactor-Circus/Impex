<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscribersAction;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscribersData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;

final class ListSubscribersMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', SubscriberModel::class);
    }

    protected function rules(): array
    {
        return ListSubscribersData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $subscribers = app(ListSubscribersAction::class)->execute(ListSubscribersData::fromValidated($validated), $this->actor());

        return SubscriberResource::collection($subscribers)->toStructuredResponse();
    }
}
