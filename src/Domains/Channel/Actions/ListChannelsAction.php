<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelsListedActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelsListingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;

final class ListChannelsAction
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'direction' => ['sometimes', Rule::enum(Direction::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Model|null  $viewer  When given, the application's channels and
     *                              the viewer's own, never another owner's.
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $filters = [], ?Model $viewer = null): array
    {
        ChannelsListingActionEvent::dispatch();

        $result = $this->perform($filters, $viewer);

        ChannelsListedActionEvent::dispatch($result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function perform(array $filters, ?Model $viewer): array
    {
        $direction = isset($filters['direction']) ? Direction::tryFrom((string) $filters['direction']) : null;

        $visible = array_filter($this->channels->all(), function (ChannelConfig $channel) use ($direction, $viewer): bool {
            if ($direction !== null && $channel->direction !== $direction) {
                return false;
            }

            return $viewer === null
                || ! $channel->isOwned()
                || ($channel->ownerType === $viewer->getMorphClass() && $channel->ownerId === (string) $viewer->getKey());
        });

        // Never the secrets themselves: describe() leaves them out.
        return array_values(array_map(fn (ChannelConfig $channel): array => $channel->describe(), $visible));
    }
}
