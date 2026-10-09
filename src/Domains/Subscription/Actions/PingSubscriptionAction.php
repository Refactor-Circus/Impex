<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Support\Str;
use RefactorCircus\Impex\Domains\Channel\Data\Receipt;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionPingedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionPingingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Exceptions\SubscriptionException;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher;

final class PingSubscriptionAction
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * Send an empty, signed `ping` to the subscription's endpoint, so a
     * subscriber can check their receiver before real events arrive.
     */
    public function execute(SubscriptionModel $subscription): Receipt
    {
        SubscriptionPingingActionEvent::dispatch($subscription);

        if (! $subscription->pushes()) {
            throw SubscriptionException::notPushed($subscription->id);
        }

        $receipt = $this->dispatcher->send(
            $subscription,
            $this->dispatcher->envelope($subscription, [], 'ping'),
            'ping.'.strtolower((string) Str::ulid()),
        );

        SubscriptionPingedActionEvent::dispatch($subscription, $receipt);

        return $receipt;
    }
}
