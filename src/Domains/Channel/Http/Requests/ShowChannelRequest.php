<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Channel\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Channel\Actions\ShowChannelAction;
use JayI\Impex\Domains\Channel\Models\ChannelModel;
use JayI\Impex\Domains\Channel\Resources\ChannelResource;

final class ShowChannelRequest extends Request
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        // A configured channel has no model and no owner: it is the
        // application's, readable by anyone signed in.
        $stored = ChannelModel::query()->where('name', $this->name())->first();

        return ! $stored instanceof ChannelModel || $this->allows('view', $stored);
    }

    public function rules(): array
    {
        return ShowChannelAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new ChannelResource(app(ShowChannelAction::class)->execute($this->name())))->response();
    }

    private function name(): string
    {
        $name = $this->route('name');

        return is_string($name) ? $name : '';
    }
}
