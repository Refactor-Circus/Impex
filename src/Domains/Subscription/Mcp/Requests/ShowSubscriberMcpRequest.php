<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ShowSubscriberAction;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;

final class ShowSubscriberMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscriber());
    }

    protected function rules(): array
    {
        return [
            'subscriber' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        return (new SubscriberResource(app(ShowSubscriberAction::class)->execute($this->subscriber())))->toStructuredResponse();
    }
}
