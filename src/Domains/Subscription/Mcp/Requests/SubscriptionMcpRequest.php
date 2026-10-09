<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Mcp\Requests;

use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Keystone\Mcp\Requests\Request;

/**
 * A tool call about a subscription or subscriber, named by `subscription`
 * or `subscriber`.
 */
abstract class SubscriptionMcpRequest extends Request
{
    protected function subscription(): SubscriptionModel
    {
        /** @var string $id */
        $id = $this->get('subscription');

        return SubscriptionModel::query()->findOrFail($id);
    }

    protected function subscriber(): SubscriberModel
    {
        /** @var string $id */
        $id = $this->get('subscriber');

        return SubscriberModel::query()->findOrFail($id);
    }
}
