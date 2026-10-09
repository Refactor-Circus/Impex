<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionExportedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionExportingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionJobs;

final class ExportSubscriptionAction
{
    public function __construct(private readonly SubscriptionJobs $jobs) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * Queue a full export of everything the subscription covers. When it is
     * written, the subscription's last_export_path names the file and its
     * cursor has moved past everything the file contains.
     */
    public function execute(SubscriptionModel $subscription): SubscriptionModel
    {
        SubscriptionExportingActionEvent::dispatch($subscription);

        $this->jobs->export($subscription->id);

        SubscriptionExportedActionEvent::dispatch($subscription);

        return $subscription;
    }
}
