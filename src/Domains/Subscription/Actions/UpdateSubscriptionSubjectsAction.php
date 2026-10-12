<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Database\ConnectionInterface;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriptionSubjectsData;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionSubjectsUpdatedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionSubjectsUpdatingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\Holdings;
use RefactorCircus\Impex\Support\Tables;

final class UpdateSubscriptionSubjectsAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Holdings $holdings,
    ) {}

    /**
     * Add keys to, or remove them from, the subscription's explicit list. A
     * subscription given a list follows only that list.
     *
     * @return array{added: int, removed: int}
     */
    public function execute(SubscriptionModel $subscription, UpdateSubscriptionSubjectsData $data): array
    {
        SubscriptionSubjectsUpdatingActionEvent::dispatch($subscription, $data);

        $add = array_values(array_unique($data->add));
        $remove = array_values(array_unique($data->remove));

        $counts = $this->db->transaction(function () use ($subscription, $add, $remove): array {
            $added = 0;
            $removed = 0;

            foreach (array_chunk($add, 1000) as $chunk) {
                $added += $this->db->table(Tables::name('subscription_subjects'))->insertOrIgnore(array_map(
                    fn (string $key): array => ['subscription_id' => $subscription->id, 'subject_key' => $key],
                    $chunk,
                ));
            }

            foreach (array_chunk($remove, 1000) as $chunk) {
                $removed += $this->db->table(Tables::name('subscription_subjects'))
                    ->where('subscription_id', $subscription->id)
                    ->whereIn('subject_key', $chunk)
                    ->delete();
            }

            if ($add !== [] && $subscription->selection !== Selection::List) {
                $from = $subscription->selection;

                $subscription->update(['selection' => Selection::List]);

                $this->holdings->selectionChanged($subscription->id, $from, Selection::List);
            }

            return ['added' => $added, 'removed' => $removed];
        });

        SubscriptionSubjectsUpdatedActionEvent::dispatch($subscription, $counts);

        return $counts;
    }
}
