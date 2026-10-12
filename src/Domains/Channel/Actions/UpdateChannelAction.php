<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Channel\Data\UpdateChannelData;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelUpdatedActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelUpdatingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;
use RefactorCircus\Impex\Domains\Channel\Services\TransportManager;
use RefactorCircus\Impex\Domains\Channel\Support\EndpointGuard;

final class UpdateChannelAction
{
    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly TransportManager $transports,
        private readonly EndpointGuard $guard,
    ) {}

    public function execute(ChannelModel $channel, UpdateChannelData $data): ChannelModel
    {
        ChannelUpdatingActionEvent::dispatch($channel, $data);

        $result = $this->perform($channel, $data);

        ChannelUpdatedActionEvent::dispatch($result);

        return $result;
    }

    private function perform(ChannelModel $channel, UpdateChannelData $data): ChannelModel
    {
        $transport = is_string($data->transport) ? $data->transport : $channel->transport;

        if (! $this->transports->has($transport)) {
            throw ChannelUnavailableException::unknownTransport($channel->name, $transport);
        }

        $options = is_array($data->options) ? $data->options : null;

        if ($channel->owner_type !== null && $transport === 'http' && is_string($options['url'] ?? null) && config('impex.outbound.guard', true) !== false) {
            $this->guard->resolve($options['url'], config('impex.outbound.allow_insecure') === true);
        }

        DB::transaction(fn (): bool => $channel->fill($data->toArray())->save());

        $this->channels->flush();

        return $channel;
    }
}
