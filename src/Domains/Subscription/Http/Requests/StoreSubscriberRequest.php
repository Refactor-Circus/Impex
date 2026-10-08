<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Subscription\Actions\CreateSubscriberAction;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Resources\SubscriberResource;

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
