<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Channel\Actions\CreateChannelAction;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;
use RefactorCircus\Keystone\Http\Requests\Request;

final class StoreChannelRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', ChannelModel::class);
    }

    public function rules(): array
    {
        return CreateChannelAction::rules();
    }

    public function persist(): JsonResponse
    {
        $channel = app(CreateChannelAction::class)->execute($this->validated(), $this->actor());

        return (new ChannelResource($channel))->response()->setStatusCode(201);
    }
}
