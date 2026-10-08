<?php

declare(strict_types=1);

namespace JayI\Impex\Domains\Subscription\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Impex\Domains\Subscription\Actions\DeleteSubscriberAction;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;

final class DestroySubscriberRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('delete', $this->subscriber());
    }

    public function rules(): array
    {
        return DeleteSubscriberAction::rules();
    }

    public function persist(): JsonResponse
    {
        app(DeleteSubscriberAction::class)->execute($this->subscriber());

        return new JsonResponse(null, 204);
    }

    private function subscriber(): SubscriberModel
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof SubscriberModel ? $subscriber : abort(404);
    }
}
