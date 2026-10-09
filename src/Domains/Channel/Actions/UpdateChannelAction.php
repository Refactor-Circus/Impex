<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Enums\ChannelStatus;
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

    /**
     * The name and direction are fixed: ledger rows carry the name, and a
     * channel turned around would leave its history describing the wrong way.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'transport' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', Rule::enum(ChannelStatus::class)],
            'body_policy' => ['sometimes', Rule::enum(BodyPolicy::class)],
            'options' => ['sometimes', 'array'],
            'credentials' => ['sometimes', 'array'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ChannelModel $channel, array $data): ChannelModel
    {
        ChannelUpdatingActionEvent::dispatch($channel, $data);

        $result = $this->perform($channel, $data);

        ChannelUpdatedActionEvent::dispatch($result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function perform(ChannelModel $channel, array $data): ChannelModel
    {
        $transport = (string) ($data['transport'] ?? $channel->transport);

        if (! $this->transports->has($transport)) {
            throw ChannelUnavailableException::unknownTransport($channel->name, $transport);
        }

        /** @var array<string, mixed>|null $options */
        $options = $data['options'] ?? null;

        if ($channel->owner_type !== null && $transport === 'http' && is_string($options['url'] ?? null) && config('impex.outbound.guard', true) !== false) {
            $this->guard->resolve($options['url'], config('impex.outbound.allow_insecure') === true);
        }

        $channel->fill(array_intersect_key($data, array_flip(['transport', 'status', 'body_policy', 'options', 'credentials'])));
        $channel->save();

        $this->channels->flush();

        return $channel;
    }
}
