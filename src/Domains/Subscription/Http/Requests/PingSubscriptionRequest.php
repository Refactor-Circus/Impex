<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Impex\Domains\Subscription\Actions\PingSubscriptionAction;

final class PingSubscriptionRequest extends SubscriptionRequest
{
    public function rules(): array
    {
        return PingSubscriptionAction::rules();
    }

    public function persist(): JsonResponse
    {
        $receipt = app(PingSubscriptionAction::class)->execute($this->subscription());

        return new JsonResponse(['data' => [
            'successful' => $receipt->successful,
            'status_code' => $receipt->statusCode,
            'duration_ms' => $receipt->durationMs,
            'error' => $receipt->error,
            'message_id' => $receipt->messageId,
        ]]);
    }

    protected function allowsOperator(): bool
    {
        return $this->allows('update', $this->subscription());
    }
}
