<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Channel\Actions\ListChannelsAction;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Keystone\Http\Requests\Request;

final class IndexChannelsRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', ChannelModel::class);
    }

    public function rules(): array
    {
        return ListChannelsAction::rules();
    }

    public function persist(): JsonResponse
    {
        return new JsonResponse(['data' => app(ListChannelsAction::class)->execute($this->validated(), $this->actor())]);
    }
}
