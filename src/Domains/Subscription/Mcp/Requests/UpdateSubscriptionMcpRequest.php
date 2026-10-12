<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\UpdateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class UpdateSubscriptionMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscription());
    }

    protected function rules(): array
    {
        return [
            'subscription' => ['required', 'string', 'max:26'],
        ] + UpdateSubscriptionData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return (new SubscriptionResource(app(UpdateSubscriptionAction::class)->execute($this->subscription(), UpdateSubscriptionData::fromValidated($validated))))->toStructuredResponse();
    }
}
