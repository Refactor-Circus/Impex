<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Data\CreateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class CreateSubscriptionMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', SubscriptionModel::class, [$this->subscriber()]);
    }

    protected function rules(): array
    {
        return [
            'subscriber' => ['required', 'string', 'max:26'],
        ] + CreateSubscriptionData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $created = app(CreateSubscriptionAction::class)->execute($this->subscriber(), CreateSubscriptionData::fromValidated($validated));

        return (new SubscriptionResource($created['subscription']))
            ->additional(['secret' => $created['secret']])
            ->toStructuredResponse();
    }
}
