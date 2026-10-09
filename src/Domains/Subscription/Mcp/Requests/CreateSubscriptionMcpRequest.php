<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
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
        return CreateSubscriptionAction::rules() + [
            'subscriber' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        unset($validated['subscriber']);

        $created = app(CreateSubscriptionAction::class)->execute($this->subscriber(), $validated);

        return Response::structured([
            ...(new SubscriptionResource($created['subscription']))->resolve(),
            'secret' => $created['secret'],
        ]);
    }
}
