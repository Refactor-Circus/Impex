<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriberAction;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;
use RefactorCircus\Keystone\Http\Requests\Request;

final class StoreSubscriberRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', SubscriberModel::class);
    }

    public function rules(): array
    {
        return CreateSubscriberAction::rules();
    }

    public function persist(): JsonResponse
    {
        $subscriber = app(CreateSubscriberAction::class)->execute($this->validated(), $this->actor());

        return (new SubscriberResource($subscriber))->response()->setStatusCode(201);
    }
}
