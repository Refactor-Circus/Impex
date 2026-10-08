<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Actions;

use JayI\Impex\Domains\Channel\Data\ChannelConfig;
use JayI\Impex\Domains\Channel\Events\ChannelShowingActionEvent;
use JayI\Impex\Domains\Channel\Events\ChannelShownActionEvent;
use JayI\Impex\Domains\Channel\Services\ChannelRegistry;

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
