<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Channel\Actions\CreateChannelAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;

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
