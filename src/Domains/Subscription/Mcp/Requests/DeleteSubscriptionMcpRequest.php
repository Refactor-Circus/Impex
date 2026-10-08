<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\DeleteSubscriptionAction;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class DeleteSubscriptionMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('delete', $this->subscription());
    }

    protected function rules(): array
    {
        return DeleteSubscriptionAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        app(DeleteSubscriptionAction::class)->execute($this->subscription());

        return Response::structured(['deleted' => $validated['subscription']]);
    }
}
