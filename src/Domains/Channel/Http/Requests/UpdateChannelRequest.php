<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Channel\Actions\UpdateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;
use RefactorCircus\Keystone\Http\Requests\Request;

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
