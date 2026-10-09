<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Channel\Enums\BodyPolicy;
use RefactorCircus\Impex\Domains\Channel\Enums\ChannelStatus;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelCreatedActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelCreatingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Exceptions\ChannelUnavailableException;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;
use RefactorCircus\Impex\Domains\Channel\Services\TransportManager;
use RefactorCircus\Impex\Domains\Channel\Support\EndpointGuard;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;

final class CreateChannelAction
{
    public function __construct(
        private readonly ChannelRegistry $channels,
        private readonly TransportManager $transports,
        private readonly EndpointGuard $guard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            // Lowercase, dotted or dashed: the name appears in URLs and on
            // every ledger row, so it is never renamed once created.
            'name' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique('impex_channels', 'name')],
            'direction' => ['required', Rule::enum(Direction::class)],
            'transport' => ['required', 'string', 'max:32'],
            'status' => ['sometimes', Rule::enum(ChannelStatus::class)],
            'body_policy' => ['sometimes', Rule::enum(BodyPolicy::class)],
            'options' => ['sometimes', 'array'],
            'credentials' => ['sometimes', 'array'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Model|null  $owner  Who the channel belongs to, such as the
     *                             subscriber registering it.
     */
    public function execute(array $data, ?Model $owner = null): ChannelModel
    {
        ChannelCreatingActionEvent::dispatch($data, $owner);

        $result = $this->perform($data, $owner);

        ChannelCreatedActionEvent::dispatch($result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function perform(array $data, ?Model $owner): ChannelModel
    {
        $name = (string) $data['name'];
        $transport = (string) $data['transport'];

        // A configured channel of the same name would be silently shadowed.
        if (array_key_exists($name, $this->channels->configured())) {
            throw ChannelUnavailableException::notStored($name);
        }

        if (! $this->transports->has($transport)) {
            throw ChannelUnavailableException::unknownTransport($name, $transport);
        }

        /** @var array<string, mixed> $options */
        $options = $data['options'] ?? [];

        // An outside party's endpoint is checked before it is ever stored, so
        // a bad URL is refused to the person entering it, not discovered at
        // the first delivery.
        if ($owner !== null && $transport === 'http' && is_string($options['url'] ?? null) && config('impex.outbound.guard', true) !== false) {
            $this->guard->resolve($options['url'], config('impex.outbound.allow_insecure') === true);
        }

        $channel = ChannelModel::query()->create([
            'name' => $name,
            'direction' => $data['direction'],
            'transport' => $transport,
            'status' => $data['status'] ?? ChannelStatus::Active,
            'body_policy' => $data['body_policy'] ?? BodyPolicy::All,
            'options' => $options === [] ? null : $options,
            'credentials' => $data['credentials'] ?? null,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner === null ? null : (string) $owner->getKey(),
        ]);

        $this->channels->flush();

        return $channel;
    }
}
