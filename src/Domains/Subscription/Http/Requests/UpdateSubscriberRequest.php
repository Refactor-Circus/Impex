<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Subscription\Actions\UpdateSubscriberAction;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;

final class UpdateSubscriberRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('update', $this->subscriber());
    }

    public function rules(): array
    {
        return UpdateSubscriberAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new SubscriberResource(app(UpdateSubscriberAction::class)->execute($this->subscriber(), $this->validated())))->response();
    }

    private function subscriber(): SubscriberModel
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof SubscriberModel ? $subscriber : abort(404);
    }
}
