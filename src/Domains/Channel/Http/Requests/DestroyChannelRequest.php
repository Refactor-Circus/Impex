<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Channel\Actions\DeleteChannelAction;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;

final class DestroyChannelRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('delete', $this->channel());
    }

    public function rules(): array
    {
        return DeleteChannelAction::rules();
    }

    public function persist(): JsonResponse
    {
        app(DeleteChannelAction::class)->execute($this->channel());

        return new JsonResponse(null, 204);
    }

    private function channel(): ChannelModel
    {
        $channel = $this->route('channel');

        return $channel instanceof ChannelModel ? $channel : abort(404);
    }
}
