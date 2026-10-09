<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Data;

use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Enums\ChannelStatus;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Support\HmacSha256Validator;
use RefactorCircus\Impex\Domains\Channel\Support\ProcessEverything;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;

/**
 * One named boundary: a way in, or a way out.
 *
 * Read from `impex.channels` for the channels an application defines in code,
 * or from `impex_channels` for those created at runtime, such as the webhook
 * endpoint a subscriber registers. Both look the same from here on.
 */
final readonly class ChannelConfig
{
    /**
     * @param  array<int, string>  $storeHeaders
     * @param  array<string, mixed>  $options  Transport settings: a URL, a
     *                                         mailer, a disk.
     * @param  array<string, mixed>  $credentials  Secrets. Never rendered.
     */
    public function __construct(
        public string $name,
        public Direction $direction = Direction::Inbound,
        public string $transport = 'http',
        public ?string $signingSecret = null,
        public string $signatureHeader = 'X-Signature',
        public string $signatureValidator = HmacSha256Validator::class,
        public string $profile = ProcessEverything::class,
        public ?string $flow = null,
        public array $storeHeaders = [],
        public ?string $queue = null,
        public ?string $path = null,
        public ?string $idempotencyHeader = null,
        public array $options = [],
        public array $credentials = [],
        public BodyPolicy $bodyPolicy = BodyPolicy::All,
        public ChannelStatus $status = ChannelStatus::Active,
        public ?string $id = null,
        public ?string $ownerType = null,
        public ?string $ownerId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        /** @var array<int, string> $storeHeaders */
        $storeHeaders = $config['store_headers'] ?? [];

        /** @var array<string, mixed> $options */
        $options = $config['options'] ?? [];

        /** @var array<string, mixed> $credentials */
        $credentials = $config['credentials'] ?? [];

        return new self(
            name: $name,
            direction: Direction::tryFrom(self::string($config, 'direction') ?? 'inbound') ?? Direction::Inbound,
            transport: self::string($config, 'transport') ?? 'http',
            signingSecret: self::string($config, 'signing_secret') ?? self::string($credentials, 'signing_secret'),
            signatureHeader: self::string($config, 'signature_header') ?? 'X-Signature',
            signatureValidator: self::string($config, 'signature_validator') ?? HmacSha256Validator::class,
            profile: self::string($config, 'profile') ?? ProcessEverything::class,
            flow: self::string($config, 'flow'),
            storeHeaders: $storeHeaders,
            queue: self::string($config, 'queue'),
            path: self::string($config, 'path'),
            idempotencyHeader: self::string($config, 'idempotency_header'),
            options: $options,
            credentials: $credentials,
            bodyPolicy: BodyPolicy::tryFrom(self::string($config, 'body_policy') ?? 'all') ?? BodyPolicy::All,
            status: ChannelStatus::tryFrom(self::string($config, 'status') ?? 'active') ?? ChannelStatus::Active,
        );
    }

    /**
     * A channel stored in `impex_channels`. Its inbound settings sit in its
     * options, and its signing secrets in its credentials.
     */
    public static function fromModel(ChannelModel $channel): self
    {
        $options = $channel->options ?? [];
        $credentials = $channel->credentials ?? [];

        /** @var array<int, string> $storeHeaders */
        $storeHeaders = $options['store_headers'] ?? [];

        return new self(
            name: $channel->name,
            direction: $channel->direction,
            transport: $channel->transport,
            signingSecret: self::string($credentials, 'signing_secret'),
            signatureHeader: self::string($options, 'signature_header') ?? 'X-Signature',
            signatureValidator: self::string($options, 'signature_validator') ?? HmacSha256Validator::class,
            profile: self::string($options, 'profile') ?? ProcessEverything::class,
            flow: self::string($options, 'flow'),
            storeHeaders: $storeHeaders,
            queue: self::string($options, 'queue'),
            idempotencyHeader: self::string($options, 'idempotency_header'),
            options: $options,
            credentials: $credentials,
            bodyPolicy: $channel->body_policy,
            status: $channel->status,
            id: $channel->id,
            ownerType: $channel->owner_type,
            ownerId: $channel->owner_id,
        );
    }

    /**
     * Whether the channel verifies signatures at all.
     */
    public function verifiesSignatures(): bool
    {
        return $this->secrets() !== [];
    }

    /**
     * Every secret the channel accepts, current first.
     *
     * During a rotation a channel holds two: the new one signs, and either
     * verifies, so the other side can switch over without a window in which
     * its deliveries are refused.
     *
     * @return array<int, string>
     */
    public function secrets(): array
    {
        $secrets = $this->credentials['secrets'] ?? [];
        $secrets = is_array($secrets) ? array_values($secrets) : [];

        if ($this->signingSecret !== null) {
            array_unshift($secrets, $this->signingSecret);
        }

        /** @var array<int, string> $kept */
        $kept = array_values(array_unique(array_filter(
            $secrets,
            fn (mixed $secret): bool => is_string($secret) && $secret !== '',
        )));

        return $kept;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function isActive(): bool
    {
        return $this->status === ChannelStatus::Active;
    }

    /**
     * Whether the channel lives in the database rather than in config, and so
     * can be changed at runtime.
     */
    public function isStored(): bool
    {
        return $this->id !== null;
    }

    /**
     * Whether someone outside the application supplied this channel, such as
     * a subscriber registering their own webhook URL. Such endpoints are held
     * to the outbound guard, which refuses private and reserved addresses.
     */
    public function isOwned(): bool
    {
        return $this->ownerType !== null;
    }

    /**
     * What the API and MCP show of a channel. Never its secrets.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'direction' => $this->direction->value,
            'transport' => $this->transport,
            'status' => $this->status->value,
            'body_policy' => $this->bodyPolicy->value,
            'verifies_signatures' => $this->verifiesSignatures(),
            'flow' => $this->flow,
            'path' => $this->path,
            'options' => $this->options,
            'stored' => $this->isStored(),
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
