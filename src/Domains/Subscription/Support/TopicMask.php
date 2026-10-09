<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Support;

/**
 * Topics as bits, so matching an event to a subscription is one AND.
 *
 * Bit positions follow a stream's topics() order; that is why the order is
 * permanent.
 */
final class TopicMask
{
    public const int MAX_TOPICS = 31;

    /**
     * @param  list<string>  $topics  The stream's topics.
     * @param  list<string>  $chosen
     */
    public static function of(array $topics, array $chosen): int
    {
        $mask = 0;

        foreach ($topics as $bit => $topic) {
            if (in_array($topic, $chosen, true)) {
                $mask |= 1 << $bit;
            }
        }

        return $mask;
    }

    /**
     * @param  list<string>  $topics
     */
    public static function all(array $topics): int
    {
        return (1 << min(count($topics), self::MAX_TOPICS)) - 1;
    }

    /**
     * @param  list<string>  $topics
     * @return list<string>
     */
    public static function names(array $topics, int $mask): array
    {
        $names = [];

        foreach ($topics as $bit => $topic) {
            if (($mask & (1 << $bit)) !== 0) {
                $names[] = $topic;
            }
        }

        return $names;
    }
}
