<div class="flex flex-col gap-4">
    <x-atrium::card :title="__('impex::impex.retention')">
        <x-atrium::description-list>
            @foreach ($retention as $key => $days)
                <x-atrium::description-list.item :term="str($key)->headline()">{{ __('impex::impex.days', ['count' => $days]) }}</x-atrium::description-list.item>
            @endforeach
        </x-atrium::description-list>
    </x-atrium::card>

    <x-atrium::card :title="__('impex::impex.limits')">
        <x-atrium::description-list>
            @foreach ($limits as $key => $value)
                <x-atrium::description-list.item :term="str($key)->headline()" class="tabular-nums">{{ $value }}</x-atrium::description-list.item>
            @endforeach
        </x-atrium::description-list>
    </x-atrium::card>

    <x-atrium::card :title="__('impex::impex.artifacts')">
        <x-atrium::description-list>
            @foreach ($artifacts as $key => $value)
                <x-atrium::description-list.item :term="str($key)->headline()">{{ is_scalar($value) ? $value : json_encode($value) }}</x-atrium::description-list.item>
            @endforeach

            <x-atrium::description-list.item :term="__('impex::impex.preview_bytes')">{{ __('impex::impex.bytes', ['count' => $previewBytes]) }}</x-atrium::description-list.item>
        </x-atrium::description-list>
    </x-atrium::card>
</div>
