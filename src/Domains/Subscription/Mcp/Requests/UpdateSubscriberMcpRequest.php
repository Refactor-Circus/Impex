<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\UpdateSubscriberAction;
use JayI\Impex\Domains\Subscription\Resources\SubscriberResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class UpdateSubscriberMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscriber());
    }

    protected function rules(): array
    {
        return UpdateSubscriberAction::rules() + [
            'subscriber' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        unset($validated['subscriber']);

        return Response::structured((new SubscriberResource(app(UpdateSubscriberAction::class)->execute($this->subscriber(), $validated)))->resolve());
    }
}
