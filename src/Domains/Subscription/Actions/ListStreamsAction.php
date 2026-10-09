<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Actions;

use RefactorCircus\Impex\Domains\Subscription\Contracts\Stream;
use RefactorCircus\Impex\Domains\Subscription\Events\StreamsListedActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Events\StreamsListingActionEvent;
use RefactorCircus\Impex\Domains\Subscription\Services\StreamRegistry;

final class ListStreamsAction
{
    public function __construct(private readonly StreamRegistry $streams) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * What a subscriber can follow, and how.
     *
     * @return array<int, array<string, mixed>>
     */
    public function execute(): array
    {
        StreamsListingActionEvent::dispatch();

        $result = array_values(array_map(fn (Stream $stream): array => [
            'key' => $stream->key(),
            'kind' => $stream->kind()->value,
            'topics' => $stream->topics(),
            'formats' => array_keys($stream->formats()),
            'filters' => array_values(array_unique(array_map(
                fn (string $rule): string => explode('.', $rule)[1] ?? $rule,
                array_keys($stream->filterRules()),
            ))),
        ], $this->streams->all()));

        StreamsListedActionEvent::dispatch($result);

        return $result;
    }
}
