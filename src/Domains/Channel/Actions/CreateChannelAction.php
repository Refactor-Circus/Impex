<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Impex\Domains\Channel\Data\CreateChannelData;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelCreatedActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelCreatingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;
use RefactorCircus\Impex\Domains\Channel\Services\TransportManager;
use RefactorCircus\Impex\Domains\Channel\Support\EndpointGuard;

final class CreateChannelAction
{
    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly TransportManager $transports,
        private readonly EndpointGuard $guard,
    ) {}

    /**
     * @param  Model|null  $owner  Who the channel belongs to, such as the
     *                             subscriber registering it.
     */
    public function execute(CreateChannelData $data, ?Model $owner = null): ChannelModel
    {
        ChannelCreatingActionEvent::dispatch($data, $owner);

        $result = $this->perform($data, $owner);

        ChannelCreatedActionEvent::dispatch($result);

        return $result;
    }

    private function perform(CreateChannelData $data, ?Model $owner): ChannelModel
    {
        $name = $data->name;
        $transport = $data->transport;

        // A configured channel of the same name would be silently shadowed.
        if (array_key_exists($name, $this->channels->configured())) {
            throw ChannelUnavailableException::notStored($name);
        }

        if (! $this->transports->has($transport)) {
            throw ChannelUnavailableException::unknownTransport($name, $transport);
        }

        $options = $data->options;

        // An outside party's endpoint is checked before it is ever stored, so
        // a bad URL is refused to the person entering it, not discovered at
        // the first delivery.
        if ($owner !== null && $transport === 'http' && is_string($options['url'] ?? null) && config('impex.outbound.guard', true) !== false) {
            $this->guard->resolve($options['url'], config('impex.outbound.allow_insecure') === true);
        }

        $channel = DB::transaction(fn (): ChannelModel => ChannelModel::query()->create([
            'name' => $name,
            'direction' => $data->direction,
            'transport' => $transport,
            'status' => $data->status,
            'body_policy' => $data->bodyPolicy,
            'options' => $options === [] ? null : $options,
            'credentials' => $data->credentials,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner === null ? null : (string) $owner->getKey(),
        ]));

        $this->channels->flush();

        return $channel;
    }
}
