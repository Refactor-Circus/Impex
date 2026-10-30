<?php

declare(strict_types=1);

namespace RefactorCircus\Impex\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Impex\Atrium\Http\Controllers\Concerns\AuthorizesScreens;
use RefactorCircus\Impex\Atrium\ScreenAccess;
use RefactorCircus\Impex\Atrium\Screens;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListDeliveriesAction;
use RefactorCircus\Impex\Domains\Subscription\Actions\ListSubscriptionsAction;
use RefactorCircus\Impex\Domains\Subscription\Actions\PingSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Actions\ShowSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Actions\UpdateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Data\ListDeliveriesData;
use RefactorCircus\Impex\Domains\Subscription\Data\ListSubscriptionsData;
use RefactorCircus\Impex\Domains\Subscription\Data\UpdateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Domains\Subscription\Services\StreamRegistry;
use RefactorCircus\Impex\Domains\Subscription\Support\TopicMask;

final class SubscriptionUiController
{
    use AuthorizesScreens;

    public function index(Request $request, ScreenExtensions $extensions): View
    {
        $this->authorizeScreen('viewAny', SubscriptionModel::class);

        $filters = ListSubscriptionsData::from($request->all());

        /** @var view-string $view */
        $view = 'impex::ui.subscriptions.index';

        return view($view, [
            'resolved' => $extensions->resolve(Screens::declare('impex.subscriptions', Screen::LIST, null, SubscriptionModel::class), $request),
            'subscriptions' => app(ListSubscriptionsAction::class)->execute($filters, null, ScreenAccess::viewer()),
            'filters' => $request->all(),
            'statuses' => SubscriptionStatus::cases(),
        ]);
    }

    public function show(Request $request, ScreenExtensions $extensions, SubscriptionModel $subscription): View
    {
        $this->authorizeScreen('view', $subscription);

        /** @var view-string $view */
        $view = 'impex::ui.subscriptions.show';

        $streams = app(StreamRegistry::class);

        return view($view, [
            'resolved' => $extensions->resolve(Screens::declare('impex.subscription', Screen::DETAIL, $subscription, null), $request),
            'subscription' => app(ShowSubscriptionAction::class)->execute($subscription),
            'topics' => $streams->has($subscription->stream)
                ? TopicMask::names($streams->get($subscription->stream)->topics(), $subscription->topics)
                : [],
            'deliveries' => app(ListDeliveriesAction::class)->execute($subscription, new ListDeliveriesData(perPage: 20)),
        ]);
    }

    public function pause(SubscriptionModel $subscription): RedirectResponse
    {
        return $this->update($subscription, new UpdateSubscriptionData(status: SubscriptionStatus::Paused), 'impex::impex.subscription_paused');
    }

    public function resume(SubscriptionModel $subscription): RedirectResponse
    {
        return $this->update($subscription, new UpdateSubscriptionData(status: SubscriptionStatus::Active), 'impex::impex.subscription_resumed');
    }

    public function replay(Request $request, SubscriptionModel $subscription): RedirectResponse
    {
        // Only the replay point: the form changes nothing else.
        return $this->update($subscription, UpdateSubscriptionData::from($request->only('replay_from')), 'impex::impex.subscription_replaying');
    }

    public function ping(SubscriptionModel $subscription): RedirectResponse
    {
        $this->authorizeScreen('update', $subscription);

        $receipt = app(PingSubscriptionAction::class)->execute($subscription);

        return redirect()
            ->route('atrium.impex.subscriptions.show', $subscription)
            ->with('status', $receipt->successful
                ? __('impex::impex.ping_succeeded')
                : __('impex::impex.ping_failed', ['error' => (string) ($receipt->error['message'] ?? '')]));
    }

    private function update(SubscriptionModel $subscription, UpdateSubscriptionData $data, string $message): RedirectResponse
    {
        $this->authorizeScreen('update', $subscription);

        app(UpdateSubscriptionAction::class)->execute($subscription, $data);

        return redirect()
            ->route('atrium.impex.subscriptions.show', $subscription)
            ->with('status', __($message));
    }
}
