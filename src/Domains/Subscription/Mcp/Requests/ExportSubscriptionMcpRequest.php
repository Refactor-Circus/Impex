<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Impex\Domains\Subscription\Actions\ExportSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;

final class ExportSubscriptionMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscription());
    }

    protected function rules(): array
    {
        return ExportSubscriptionAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        return Response::structured((new SubscriptionResource(app(ExportSubscriptionAction::class)->execute($this->subscription())))->resolve());
    }
}
