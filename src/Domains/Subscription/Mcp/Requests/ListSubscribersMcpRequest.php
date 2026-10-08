<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\ListSubscribersAction;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Resources\SubscriberResource;
use Laravel\Mcp\ResponseFactory;

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
