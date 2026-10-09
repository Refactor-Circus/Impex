<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Actions;

use RefactorCircus\Impex\Domains\Channel\Data\ChannelConfig;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelShowingActionEvent;
use RefactorCircus\Impex\Domains\Channel\Events\ChannelShownActionEvent;
use RefactorCircus\Impex\Domains\Channel\Services\ChannelRegistry;

final class ShowChannelAction
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    public function execute(string $name): ChannelConfig
    {
        ChannelShowingActionEvent::dispatch($name);

        $result = $this->channels->get($name);

        ChannelShownActionEvent::dispatch($result);

        return $result;
    }
}
