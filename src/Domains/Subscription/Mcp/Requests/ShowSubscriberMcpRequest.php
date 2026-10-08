<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\ShowSubscriberAction;
use JayI\Impex\Domains\Subscription\Resources\SubscriberResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class ShowSubscriberMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscriber());
    }

    protected function rules(): array
    {
        return ShowSubscriberAction::rules() + [
            'subscriber' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        return Response::structured((new SubscriberResource(app(ShowSubscriberAction::class)->execute($this->subscriber())))->resolve());
    }
}
