<x-atrium::layout :title="__('impex::impex.new_channel')">
    <x-atrium::page-header :title="__('impex::impex.new_channel')" />

    <div class="mt-5 flex flex-col gap-5">
        <x-atrium::flash />

        <x-atrium::card>
            <form method="POST" action="{{ route('atrium.impex.channels.store') }}" class="flex flex-col gap-4" data-testid="create-channel">
                @csrf

                <div class="flex flex-wrap items-end gap-3">
                    <x-atrium::form.input name="name" :label="__('impex::impex.name')" :value="old('name')" :hint="__('impex::impex.channel_name_hint')" required wrapper="w-72" />

                    <x-atrium::form.select
                        name="direction"
                        :label="__('impex::impex.direction')"
                        :options="collect($directions)->mapWithKeys(fn ($direction) => [$direction->value => $direction->value])"
                        :selected="old('direction', 'outbound')"
                        wrapper="w-44" />
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    @include('impex::ui.channels.partials.fields', ['channel' => null])
                </div>

                <x-atrium::form.actions>
                    <x-atrium::button type="submit">{{ __('impex::impex.create') }}</x-atrium::button>
                    <x-atrium::button variant="ghost" :href="route('atrium.impex.channels.index')">{{ __('impex::impex.cancel') }}</x-atrium::button>
                </x-atrium::form.actions>
            </form>
        </x-atrium::card>
    </div>
</x-atrium::layout>
