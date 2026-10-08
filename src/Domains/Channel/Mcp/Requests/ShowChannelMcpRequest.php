<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Mcp\Requests;

use JayI\Impex\Domains\Channel\Actions\ShowChannelAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class ShowChannelMcpRequest extends ChannelMcpRequest
{
    protected function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $stored = ChannelModel::query()->where('name', $this->name())->first();

        return ! $stored instanceof ChannelModel || $this->allows('view', $stored);
    }

    protected function rules(): array
    {
        return ShowChannelAction::rules() + [
            'channel' => ['required', 'string', 'max:191'],
        ];
    }

    protected function handle(array $validated): ResponseFactory
    {
        $channel = app(ShowChannelAction::class)->execute($this->name());

        return Response::structured((new ChannelResource($channel))->resolve());
    }

    private function name(): string
    {
        /** @var string $name */
        $name = $this->get('channel');

        return $name;
    }
}
