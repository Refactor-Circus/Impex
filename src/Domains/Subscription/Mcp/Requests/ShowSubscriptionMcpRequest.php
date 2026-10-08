<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\ShowSubscriptionAction;
use JayI\Impex\Domains\Subscription\Resources\SubscriptionResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class ShowSubscriptionMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscription());
    }

    protected function rules(): array
    {
        return ShowSubscriptionAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        return Response::structured((new SubscriptionResource(app(ShowSubscriptionAction::class)->execute($this->subscription())))->resolve());
    }
}
