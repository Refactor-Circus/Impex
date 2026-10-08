<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Subscription\Actions\ListSubscribersAction;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Resources\SubscriberResource;

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
