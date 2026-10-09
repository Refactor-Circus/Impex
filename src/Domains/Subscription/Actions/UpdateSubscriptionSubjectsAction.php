<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use Illuminate\Database\ConnectionInterface;
use RefactorCircus\Impex\Domains\Subscription\Enums\Selection;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionSubjectsUpdatedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\SubscriptionSubjectsUpdatingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

final class UpdateSubscriptionSubjectsAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'add' => ['sometimes', 'array', 'max:10000'],
            'add.*' => ['string', 'max:191'],
            'remove' => ['sometimes', 'array', 'max:10000'],
            'remove.*' => ['string', 'max:191'],
        ];
    }

    /**
     * Add keys to, or remove them from, the subscription's explicit list. A
     * subscription given a list follows only that list.
     *
     * @param  array<string, mixed>  $data
     * @return array{added: int, removed: int}
     */
    public function execute(SubscriptionModel $subscription, array $data): array
    {
        SubscriptionSubjectsUpdatingActionEvent::dispatch($subscription, $data);

        /** @var list<string> $add */
        $add = array_values(array_unique($data['add'] ?? []));

        /** @var list<string> $remove */
        $remove = array_values(array_unique($data['remove'] ?? []));

        $added = 0;
        $removed = 0;

        foreach (array_chunk($add, 1000) as $chunk) {
            $added += $this->db->table('impex_subscription_subjects')->insertOrIgnore(array_map(
                fn (string $key): array => ['subscription_id' => $subscription->id, 'subject_key' => $key],
                $chunk,
            ));
        }

        foreach (array_chunk($remove, 1000) as $chunk) {
            $removed += $this->db->table('impex_subscription_subjects')
                ->where('subscription_id', $subscription->id)
                ->whereIn('subject_key', $chunk)
                ->delete();
        }

        if ($add !== [] && $subscription->selection !== Selection::List) {
            $subscription->update(['selection' => Selection::List]);
        }

        $counts = ['added' => $added, 'removed' => $removed];

        SubscriptionSubjectsUpdatedActionEvent::dispatch($subscription, $counts);

        return $counts;
    }
}
