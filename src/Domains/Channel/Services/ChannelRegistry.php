<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Services;

use Illuminate\Contracts\Config\Repository as Config;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Exceptions\UnknownChannelException;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;

/**
 * The catalogue of named boundaries.
 *
 * Two sources, one catalogue: the channels an application defines in
 * `impex.channels`, and those stored in `impex_channels` at runtime. A stored
 * channel of the same name wins, so an operator can retune a configured one
 * without a deploy.
 */
final class ChannelRegistry
{
    /**
     * @var array<string, ChannelConfig>|null
     */
    private ?array $stored = null;

    private float $loadedAt = 0.0;

    public function __construct(private readonly Config $config) {}

    /**
     * Every channel, configured and stored.
     *
     * @return array<string, ChannelConfig>
     */
    public function all(): array
    {
        return array_merge($this->configured(), $this->stored());
    }

    /**
     * The channels defined in config only.
     *
     * Read straight from config on every call: memoizing it would freeze the
     * catalogue at the moment the container first resolved the registry,
     * which for a package registering routes at boot is before the
     * application has finished configuring itself. This is also the only
     * source the route file reads, so registering routes never touches the
     * database.
     *
     * @return array<string, ChannelConfig>
     */
    public function configured(): array
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = $this->config->get('impex.channels', []);

        $channels = [];

        foreach ($configured as $name => $channel) {
            $channels[$name] = ChannelConfig::fromArray((string) $name, $channel);
        }

        return $channels;
    }

    /**
     * @return array<string, ChannelConfig>
     */
    public function inbound(): array
    {
        return $this->direction(Direction::Inbound);
    }

    /**
     * @return array<string, ChannelConfig>
     */
    public function outbound(): array
    {
        return $this->direction(Direction::Outbound);
    }

    public function has(string $name): bool
    {
        return $this->find($name) instanceof ChannelConfig;
    }

    public function get(string $name): ChannelConfig
    {
        return $this->find($name) ?? throw UnknownChannelException::name($name);
    }

    public function find(string $name): ?ChannelConfig
    {
        return $this->stored()[$name] ?? $this->configured()[$name] ?? null;
    }

    /**
     * What a channel keeps of the bodies crossing it. Traffic on a name no
     * channel defines — an ad hoc label passed to Impex::http() — keeps
     * everything, as it always has.
     */
    public function bodyPolicy(string $name): BodyPolicy
    {
        return $this->find($name)->bodyPolicy ?? BodyPolicy::All;
    }

    /**
     * Forget the stored channels, so the next read sees a change made in this
     * process. Other processes see it once their copy ages out.
     */
    public function flush(): void
    {
        $this->stored = null;
    }

    /**
     * @return array<string, ChannelConfig>
     */
    private function direction(Direction $direction): array
    {
        return array_filter($this->all(), fn (ChannelConfig $c): bool => $c->direction === $direction);
    }

    /**
     * The stored channels, held for `impex.channel_registry.cache_seconds`.
     *
     * A long-lived worker sending a million deliveries resolves the same few
     * channels over and over; reading them once per window keeps that off the
     * database, at the cost of an edit taking up to the window to reach
     * workers other than the one that made it.
     *
     * @return array<string, ChannelConfig>
     */
    private function stored(): array
    {
        /** @var int $ttl */
        $ttl = $this->config->get('impex.channel_registry.cache_seconds', 30);

        if ($this->stored !== null && (microtime(true) - $this->loadedAt) < $ttl) {
            return $this->stored;
        }

        $channels = [];

        foreach (ChannelModel::query()->get() as $channel) {
            $channels[$channel->name] = ChannelConfig::fromModel($channel);
        }

        $this->loadedAt = microtime(true);

        return $this->stored = $channels;
    }
}
