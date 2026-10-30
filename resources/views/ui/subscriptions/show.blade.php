@use(RefactorCircus\Impex\Atrium\Badges)
@use(RefactorCircus\Impex\Domains\Subscription\Enums\SubscriptionStatus)

{{-- The `impex.subscription` detail screen: header actions always render, after any
     of the page's own; an open registered tab's panel replaces the page, and
     `before`, `after` and the `aside` column (only when it has something to
     show) render only around it. --}}
@php($aside = $resolved->openRegisteredTab === null && $resolved->sections('aside') !== [])
<x-atrium::layout :title="$subscription->stream">
    <x-atrium::page-header :title="$subscription->stream" :description="$subscription->subscriber->name">
        <x-slot:actions>
            <x-atrium::badge :variant="Badges::forSubscription($subscription->status)" data-testid="subscription-status">{{ __('impex::impex.subscription_statuses.'.$subscription->status->value) }}</x-atrium::badge>

            @impexCan('update', $subscription)
                @if ($subscription->status === SubscriptionStatus::Active)
                    <form method="POST" action="{{ route('atrium.impex.subscriptions.pause', $subscription) }}">
                        @csrf
                        <x-atrium::icon-button icon="pause" :label="__('impex::impex.pause')" variant="outline" type="submit" data-testid="pause-subscription" />
                    </form>
                @else
                    <form method="POST" action="{{ route('atrium.impex.subscriptions.resume', $subscription) }}">
                        @csrf
                        <x-atrium::icon-button icon="play" :label="__('impex::impex.resume')" variant="outline" type="submit" data-testid="resume-subscription" />
                    </form>
                @endif

                @if ($subscription->pushes())
                    <form method="POST" action="{{ route('atrium.impex.subscriptions.ping', $subscription) }}">
                        @csrf
                        <x-atrium::icon-button icon="paper-airplane" :label="__('impex::impex.ping')" variant="outline" type="submit" data-testid="ping-subscription" />
                    </form>
                @endif
            @endimpexCan

            <x-atrium::screen-sections :screen="$resolved" slot="header.actions" />
        </x-slot:actions>
    </x-atrium::page-header>

    <x-atrium::screen-tabs :screen="$resolved" class="mt-4" />

    @if ($resolved->openRegisteredTab !== null)
        <x-atrium::screen-tab-panel :screen="$resolved" class="mt-5" />
    @else
        <x-atrium::screen-sections :screen="$resolved" slot="before" class="mt-5" />

        @if ($aside)
            <div class="mt-5 grid gap-6 lg:grid-cols-3">
                <div class="flex flex-col gap-5 lg:col-span-2" data-testid="impex-content">
        @else
                <div class="mt-5 flex flex-col gap-5" data-testid="impex-content">
        @endif
                    <x-atrium::flash />

                    <x-atrium::card>
                        <x-atrium::description-list>
                            <x-atrium::description-list.item :term="__('impex::impex.topics')">{{ implode(', ', $topics) }}</x-atrium::description-list.item>
                            <x-atrium::description-list.item :term="__('impex::impex.selection')">{{ $subscription->selection->value }}</x-atrium::description-list.item>
                            <x-atrium::description-list.item :term="__('impex::impex.format')">{{ $subscription->format }}</x-atrium::description-list.item>
                            <x-atrium::description-list.item :term="__('impex::impex.endpoint')">{{ $subscription->channel->options['url'] ?? $subscription->channel->options['to'] ?? __('impex::impex.endpoint_feed_only') }}</x-atrium::description-list.item>
                            <x-atrium::description-list.item :term="__('impex::impex.cursor')" class="font-mono">{{ $subscription->cursor }}</x-atrium::description-list.item>
                            <x-atrium::description-list.item :term="__('impex::impex.failures')">{{ $subscription->failures }}</x-atrium::description-list.item>

                            @if ($subscription->paused_until?->isFuture())
                                <x-atrium::description-list.item :term="__('impex::impex.paused_until')">{{ $subscription->paused_until->diffForHumans() }}</x-atrium::description-list.item>
                            @endif

                            @if ($subscription->last_error)
                                <x-atrium::description-list.item :term="__('impex::impex.last_error')">{{ $subscription->last_error['message'] ?? '' }}</x-atrium::description-list.item>
                            @endif
                        </x-atrium::description-list>
                    </x-atrium::card>

                    @impexCan('update', $subscription)
                        <x-atrium::card>
                            <form method="POST" action="{{ route('atrium.impex.subscriptions.replay', $subscription) }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <x-atrium::form.input name="replay_from" type="number" :label="__('impex::impex.replay_from')" wrapper="w-48" />
                                <x-atrium::icon-button icon="arrow-path" :label="__('impex::impex.replay')" variant="primary" type="submit" data-testid="replay-subscription" />
                            </form>
                        </x-atrium::card>
                    @endimpexCan

                    <x-atrium::card :title="__('impex::impex.deliveries')">
                        @if ($deliveries->isEmpty())
                            <x-atrium::empty-state :title="__('impex::impex.no_deliveries')" />
                        @else
                            <x-atrium::table striped>
                                <x-slot:head>
                                    <x-atrium::table.row>
                                        <x-atrium::table.cell heading>{{ __('impex::impex.status') }}</x-atrium::table.cell>
                                        <x-atrium::table.cell heading numeric>{{ __('impex::impex.events') }}</x-atrium::table.cell>
                                        <x-atrium::table.cell heading>{{ __('impex::impex.duration') }}</x-atrium::table.cell>
                                        <x-atrium::table.cell heading>{{ __('impex::impex.when') }}</x-atrium::table.cell>
                                    </x-atrium::table.row>
                                </x-slot:head>

                                @foreach ($deliveries as $delivery)
                                    <x-atrium::table.row>
                                        <x-atrium::table.cell>
                                            <x-atrium::badge :variant="Badges::forDelivery($delivery->status)">{{ $delivery->status_code ?? $delivery->status->value }}</x-atrium::badge>
                                        </x-atrium::table.cell>
                                        <x-atrium::table.cell numeric>{{ $delivery->events }}</x-atrium::table.cell>
                                        <x-atrium::table.cell>{{ $delivery->duration_ms === null ? __('impex::impex.none') : __('impex::impex.milliseconds', ['count' => $delivery->duration_ms]) }}</x-atrium::table.cell>
                                        <x-atrium::table.cell>
                                            @if ($delivery->message_id)
                                                <a class="underline-offset-2 hover:underline" href="{{ route('atrium.impex.messages.show', $delivery->message_id) }}">{{ $delivery->attempted_at->diffForHumans() }}</a>
                                            @else
                                                {{ $delivery->attempted_at->diffForHumans() }}
                                            @endif
                                        </x-atrium::table.cell>
                                    </x-atrium::table.row>
                                @endforeach
                            </x-atrium::table>
                        @endif
                    </x-atrium::card>
                </div>

        @if ($aside)
                <aside class="flex flex-col gap-4" data-testid="screen-aside">
                    <x-atrium::screen-sections :screen="$resolved" slot="aside" />
                </aside>
            </div>
        @endif

        <x-atrium::screen-sections :screen="$resolved" slot="after" class="mt-5" />
    @endif
</x-atrium::layout>
