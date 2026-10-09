@use(RefactorCircus\Impex\Atrium\Badges)

<x-atrium::layout :title="__('impex::impex.subscriptions')">
    <x-atrium::page-header :title="__('impex::impex.subscriptions')" />

    <div class="mt-5 flex flex-col gap-4">
        <x-atrium::card>
            <form method="GET" action="{{ route('atrium.impex.subscriptions.index') }}" class="flex flex-wrap items-end gap-3">
                <x-atrium::form.input name="stream" :label="__('impex::impex.stream')" :value="$filters['stream'] ?? null" wrapper="w-48" />

                <x-atrium::form.select
                    name="status"
                    :label="__('impex::impex.status')"
                    :placeholder="__('impex::impex.none')"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => __('impex::impex.subscription_statuses.'.$status->value)])"
                    :selected="$filters['status'] ?? null"
                    wrapper="w-44" />

                <x-atrium::icon-button icon="funnel" :label="__('impex::impex.filter')" variant="primary" type="submit" data-testid="filter-subscriptions" />
                <x-atrium::icon-button icon="x-mark" :label="__('impex::impex.clear')" variant="ghost" :href="route('atrium.impex.subscriptions.index')" />
            </form>
        </x-atrium::card>

        @if ($subscriptions->isEmpty())
            <x-atrium::empty-state :title="__('impex::impex.no_subscriptions')" />
        @else
            <x-atrium::table striped>
                <x-slot:head>
                    <x-atrium::table.row>
                        <x-atrium::table.cell heading>{{ __('impex::impex.stream') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.subscriber') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.endpoint') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.status') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading numeric>{{ __('impex::impex.failures') }}</x-atrium::table.cell>
                        <x-atrium::table.cell heading>{{ __('impex::impex.last_delivered') }}</x-atrium::table.cell>
                    </x-atrium::table.row>
                </x-slot:head>

                @foreach ($subscriptions as $subscription)
                    <x-atrium::table.row>
                        <x-atrium::table.cell>
                            <a class="underline-offset-2 hover:underline"
                               href="{{ route('atrium.impex.subscriptions.show', $subscription) }}">{{ $subscription->stream }}</a>
                        </x-atrium::table.cell>
                        <x-atrium::table.cell>{{ $subscription->subscriber->name }}</x-atrium::table.cell>
                        <x-atrium::table.cell>{{ $subscription->channel->options['url'] ?? $subscription->channel->options['to'] ?? __('impex::impex.endpoint_feed_only') }}</x-atrium::table.cell>
                        <x-atrium::table.cell>
                            <x-atrium::badge :variant="Badges::forSubscription($subscription->status)">{{ __('impex::impex.subscription_statuses.'.$subscription->status->value) }}</x-atrium::badge>
                        </x-atrium::table.cell>
                        <x-atrium::table.cell numeric>{{ $subscription->failures }}</x-atrium::table.cell>
                        <x-atrium::table.cell>{{ $subscription->last_delivered_at?->diffForHumans() ?? __('impex::impex.none') }}</x-atrium::table.cell>
                    </x-atrium::table.row>
                @endforeach
            </x-atrium::table>

            <x-atrium::pagination :paginator="$subscriptions" />
        @endif
    </div>
</x-atrium::layout>
