<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Validator;
use JayI\Impex\Domains\Subscription\Contracts\Stream;
use JayI\Impex\Domains\Subscription\Enums\Selection;
use JayI\Impex\Domains\Subscription\Exceptions\SubscriptionException;

/**
 * Turns what a subscriber asked for — topic names, a format, a filter — into
 * what a subscription stores, checked against the stream it is for.
 */
final class SubscriptionSettings
{
    public function __construct(private readonly Config $config) {}

    /**
     * Only the keys present in $data, so an update changes only what it
     * names.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolve(Stream $stream, array $data, bool $creating = true): array
    {
        $settings = [];

        if (array_key_exists('topics', $data) || $creating) {
            $settings['topics'] = $this->topics($stream, $data['topics'] ?? []);
        }

        if (array_key_exists('format', $data) || $creating) {
            $settings['format'] = $this->format($stream, $data['format'] ?? null);
        }

        if (array_key_exists('filter', $data)) {
            /** @var array<string, mixed>|null $filter */
            $filter = $data['filter'];

            if ($filter !== null && $filter !== []) {
                Validator::make(['filter' => $filter], $stream->filterRules())->validate();
            }

            $settings['filter'] = $filter === [] ? null : $filter;
            $settings['selection'] = $filter === null || $filter === [] ? Selection::All : Selection::Filter;
        }

        if (($data['subjects'] ?? []) !== []) {
            $settings['selection'] = Selection::List;
        }

        if ($creating) {
            $settings['selection'] ??= Selection::All;
        }

        return $settings;
    }

    /**
     * @param  mixed  $chosen  Topic names; empty means all of them.
     */
    private function topics(Stream $stream, mixed $chosen): int
    {
        $all = $stream->topics();
        $chosen = is_array($chosen) ? array_values(array_map('strval', $chosen)) : [];

        if ($chosen === []) {
            return TopicMask::all($all);
        }

        if (array_diff($chosen, $all) !== []) {
            throw SubscriptionException::unknownTopics($stream->key(), $all);
        }

        return TopicMask::of($all, $chosen);
    }

    private function format(Stream $stream, mixed $format): string
    {
        $formats = array_keys($stream->formats());

        /** @var string $default */
        $default = $this->config->get('impex.subscriptions.default_format', 'slice');

        $format = is_string($format) ? $format : (in_array($default, $formats, true) ? $default : ($formats[0] ?? 'thin'));

        if (! in_array($format, $formats, true)) {
            throw SubscriptionException::unknownFormat($stream->key(), $format, $formats);
        }

        return $format;
    }
}
