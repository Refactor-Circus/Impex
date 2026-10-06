{{--
    A run or step status as Atrium's status dot, coloured by
    JayI\Impex\Atrium\Badges (pending is info):

        @include('impex::ui.partials.status-dot', ['status' => $run->status])

    Or pass `variant`, `label` and `state` (the `data-status` value) for any
    other state, and `testid` for a `data-testid`.
--}}
@php
    $status ??= null;
    $variant ??= \JayI\Impex\Atrium\Badges::forStatus($status);
@endphp

<x-atrium::status-dot
    :variant="$variant"
    :label="$label ?? __('impex::impex.statuses.'.$status->value)"
    :data-status="$state ?? $status?->value ?? $variant"
    :data-testid="$testid ?? null" />
