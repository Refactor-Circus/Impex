<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Subscription\Http\Middleware\ResolveSubscriber;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * A request served on both surfaces: the operator API, checked against the
 * policies, and the subscriber API, where the caller is a subscriber and may
 * touch only their own subscriptions.
 */
abstract class SubscriptionRequest extends Request
{
    public function authorize(): bool
    {
        $subscriber = $this->subscriber();

        if ($subscriber instanceof SubscriberModel) {
            $subscription = $this->route('subscription');

            return ! $subscription instanceof SubscriptionModel || $subscription->subscriber_id === $subscriber->id;
        }

        return parent::authorize() && $this->allowsOperator();
    }

    /**
     * The policy check for an operator.
     */
    abstract protected function allowsOperator(): bool;

    /**
     * The subscriber calling, on the subscriber API. Null on the operator
     * API.
     */
    protected function subscriber(): ?SubscriberModel
    {
        $subscriber = $this->attributes->get(ResolveSubscriber::ATTRIBUTE);

        return $subscriber instanceof SubscriberModel ? $subscriber : null;
    }

    protected function subscription(): SubscriptionModel
    {
        $subscription = $this->route('subscription');

        return $subscription instanceof SubscriptionModel ? $subscription : abort(404);
    }
}
