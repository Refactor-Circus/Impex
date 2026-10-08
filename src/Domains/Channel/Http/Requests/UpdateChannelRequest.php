<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Channel\Actions\UpdateChannelAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;

final class UpdateChannelRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->channel());
    }

    public function rules(): array
    {
        return UpdateChannelAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new ChannelResource(app(UpdateChannelAction::class)->execute($this->channel(), $this->validated())))->response();
    }

    private function channel(): ChannelModel
    {
        $channel = $this->route('channel');

        return $channel instanceof ChannelModel ? $channel : abort(404);
    }
}
