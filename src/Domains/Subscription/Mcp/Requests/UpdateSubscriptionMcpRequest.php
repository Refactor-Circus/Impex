<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\UpdateSubscriptionAction;
use JayI\Impex\Domains\Subscription\Resources\SubscriptionResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class UpdateSubscriptionMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscription());
    }

    protected function rules(): array
    {
        return UpdateSubscriptionAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        unset($validated['subscription']);

        return Response::structured((new SubscriptionResource(app(UpdateSubscriptionAction::class)->execute($this->subscription(), $validated)))->resolve());
    }
}
