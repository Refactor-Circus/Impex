<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscribersAction;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriberResource;

final class IndexSubscribersRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('viewAny', SubscriberModel::class);
    }

    public function rules(): array
    {
        return ListSubscribersAction::rules();
    }

    public function persist(): JsonResponse
    {
        return SubscriberResource::collection(app(ListSubscribersAction::class)->execute($this->validated(), $this->actor()))->response();
    }
}
