<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Mcp\Requests;

use JayI\Impex\Domains\Subscription\Actions\PingSubscriptionAction;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class PingSubscriptionMcpRequest extends SubscriptionMcpRequest
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscription());
    }

    protected function rules(): array
    {
        return PingSubscriptionAction::rules() + [
            'subscription' => ['required', 'string', 'max:26'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $receipt = app(PingSubscriptionAction::class)->execute($this->subscription());

        return Response::structured([
            'successful' => $receipt->successful,
            'status_code' => $receipt->statusCode,
            'duration_ms' => $receipt->durationMs,
            'error' => $receipt->error,
            'message_id' => $receipt->messageId,
        ]);
    }
}
