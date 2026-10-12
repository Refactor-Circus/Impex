<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\UpdateSubscriberAction;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriberData;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;

final class UpdateSubscriberMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscriber());
    }

    protected function rules(): array
    {
        return [
            'subscriber' => ['required', 'string', 'max:26'],
        ] + UpdateSubscriberData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return (new SubscriberResource(app(UpdateSubscriberAction::class)->execute($this->subscriber(), UpdateSubscriberData::fromValidated($validated))))->toStructuredResponse();
    }
}
