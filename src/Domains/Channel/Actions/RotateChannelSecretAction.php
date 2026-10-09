<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use RefactorCircus\Impex\Domains\Channel\Events\ChannelSecretRotatedActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelSecretRotatingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;

final class RotateChannelSecretAction
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * Give the channel a new signing secret, keeping the previous one.
     *
     * Outbound, both sign until the previous one is dropped, so a receiver
     * verifying with either keeps accepting deliveries while it switches.
     * Inbound, both verify. The next rotation drops the oldest.
     *
     * @return array{channel: ChannelModel, secret: string} The new secret,
     *                                                      returned this once.
     */
    public function execute(ChannelModel $channel): array
    {
        ChannelSecretRotatingActionEvent::dispatch($channel);

        $secret = 'whsec_'.base64_encode(random_bytes(32));
        $credentials = $channel->credentials ?? [];
        $previous = is_string($credentials['signing_secret'] ?? null) ? [$credentials['signing_secret']] : [];

        $channel->credentials = [...$credentials, 'signing_secret' => $secret, 'secrets' => $previous];
        $channel->save();
        $this->channels->flush();

        ChannelSecretRotatedActionEvent::dispatch($channel);

        return ['channel' => $channel, 'secret' => $secret];
    }
}
