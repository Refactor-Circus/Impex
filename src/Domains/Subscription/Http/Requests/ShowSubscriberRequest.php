<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Subscription\Actions\ShowSubscriberAction;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;

final class ShowSubscriberRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->subscriber());
    }

    public function rules(): array
    {
        return ShowSubscriberAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new SubscriberResource(app(ShowSubscriberAction::class)->execute($this->subscriber())))->response();
    }

    private function subscriber(): SubscriberModel
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof SubscriberModel ? $subscriber : abort(404);
    }
}
