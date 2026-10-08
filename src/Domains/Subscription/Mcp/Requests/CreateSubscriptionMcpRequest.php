<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Domains\Subscription\Resources\SubscriptionResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
