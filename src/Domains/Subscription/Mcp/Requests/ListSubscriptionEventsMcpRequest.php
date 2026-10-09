<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscriptionEventsAction;

final class ListSubscriptionEventsMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscription());
    }

    protected function rules(): array
    {
        return ListSubscriptionEventsAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        unset($validated['subscription']);

        $page = app(ListSubscriptionEventsAction::class)->execute($this->subscription(), $validated);

        return $this->structuredCollection($page['events'], ['cursor' => $page['cursor'], 'has_more' => $page['has_more']]);
    }
}
