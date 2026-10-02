{{--
    A run or step status as Atrium's status dot, coloured by
    JayI\Impex\Atrium\Badges (pending is info). Or pass `variant`, `label`
    and `data-status` for any other state.
--}}
@props([
    'status' => null,
    'variant' => null,
    'label' => null,
])

<x-atrium::status-dot
    :variant="$variant ?? \JayI\Impex\Atrium\Badges::forStatus($status)"
    :label="$label ?? __('impex::impex.statuses.'.$status->value)"
    {{ $attributes->merge(['data-status' => $status?->value ?? $variant]) }} />
