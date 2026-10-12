<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscriptionEventsAction;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionEventsData;

final class ListSubscriptionEventsMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscription());
    }

    protected function rules(): array
    {
        return [
            'subscription' => ['required', 'string', 'max:26'],
        ] + ListSubscriptionEventsData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $page = app(ListSubscriptionEventsAction::class)->execute($this->subscription(), ListSubscriptionEventsData::fromValidated($validated));

        return $this->structuredCollection($page['events'], ['cursor' => $page['cursor'], 'has_more' => $page['has_more']]);
    }
}
