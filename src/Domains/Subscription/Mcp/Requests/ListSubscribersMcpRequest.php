<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscribersAction;
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
        return ListSubscribersAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $subscribers = app(ListSubscribersAction::class)->execute($validated, $this->actor());

        return $this->structuredCollection(
            SubscriberResource::collection($subscribers->items())->resolve(),
            ['next_cursor' => $subscribers->nextCursor()?->encode()],
        );
    }
}
