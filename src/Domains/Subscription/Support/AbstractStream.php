<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Support;

use RefactorCircus\Impex\Domains\Subscription\Contracts\Stream;
use RefactorCircus\Impex\Domains\Subscription\Contracts\SubscriptionMatcher;
use RefactorCircus\Impex\Domains\Subscription\Enums\StreamKind;
use RefactorCircus\Impex\Domains\Subscription\Exceptions\SubscriptionException;
use RefactorCircus\Impex\Domains\Subscription\Formatters\FullFormatter;
use RefactorCircus\Impex\Domains\Subscription\Formatters\SliceFormatter;
use RefactorCircus\Impex\Domains\Subscription\Formatters\ThinFormatter;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;

/**
 * The defaults most streams want: snapshot kind, no filters, the three
 * standard formats, and slicing by top-level key.
 */
abstract class AbstractStream implements Stream
{
    public function kind(): StreamKind
    {
        return StreamKind::Snapshot;
    }

    /**
     * Each topic is the snapshot key of the same name.
     */
    public function slice(array $snapshot): array
    {
        $slices = [];

        foreach ($this->topics() as $topic) {
            $slices[$topic] = $snapshot[$topic] ?? null;
        }

        return $slices;
    }

    public function snapshots(array $keys): array
    {
        return [];
    }

    public function matcher(): SubscriptionMatcher
    {
        return new AllSubjectsMatcher;
    }

    public function filterRules(): array
    {
        return [];
    }

    public function formats(): array
    {
        return [
            'thin' => new ThinFormatter,
            'slice' => new SliceFormatter,
            'full' => new FullFormatter,
        ];
    }

    public function export(SubscriptionModel $subscription, mixed $handle): void
    {
        throw SubscriptionException::exportUnsupported($this->key());
    }
}
