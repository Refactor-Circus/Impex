<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\UpdateSubscriptionSubjectsAction;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class UpdateSubscriptionSubjectsMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscription());
    }

    protected function rules(): array
    {
        return UpdateSubscriptionSubjectsAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        unset($validated['subscription']);

        return Response::structured(app(UpdateSubscriptionSubjectsAction::class)->execute($this->subscription(), $validated));
    }
}
