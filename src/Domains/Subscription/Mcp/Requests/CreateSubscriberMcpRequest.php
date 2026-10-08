<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\CreateSubscriberAction;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Resources\SubscriberResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class CreateSubscriberMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', SubscriberModel::class);
    }

    protected function rules(): array
    {
        return CreateSubscriberAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        return Response::structured((new SubscriberResource(app(CreateSubscriberAction::class)->execute($validated, $this->actor())))->resolve());
    }
}
