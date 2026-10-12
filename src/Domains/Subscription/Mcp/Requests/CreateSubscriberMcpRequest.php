<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriberAction;
use RefactorCircus\Impex\Domains\Subscription\Data\CreateSubscriberData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;

final class CreateSubscriberMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', SubscriberModel::class);
    }

    protected function rules(): array
    {
        return CreateSubscriberData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return (new SubscriberResource(app(CreateSubscriberAction::class)->execute(CreateSubscriberData::fromValidated($validated), $this->actor())))->toStructuredResponse();
    }
}
