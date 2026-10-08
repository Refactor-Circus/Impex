<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Channel\Actions\RotateChannelSecretAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;

final class RotateChannelSecretRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->channel());
    }

    public function rules(): array
    {
        return RotateChannelSecretAction::rules();
    }

    /**
     * The one response that carries a secret: it is shown once, here.
     */
    public function persist(): JsonResponse
    {
        $rotated = app(RotateChannelSecretAction::class)->execute($this->channel());

        return (new ChannelResource($rotated['channel']))
            ->additional(['secret' => $rotated['secret']])
            ->response();
    }

    private function channel(): ChannelModel
    {
        $channel = $this->route('channel');

        return $channel instanceof ChannelModel ? $channel : abort(404);
    }
}
