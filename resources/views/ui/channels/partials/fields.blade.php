{{-- The fields a stored channel's create and edit forms share. --}}
<x-atrium::form.select
    name="transport"
    :label="__('impex::impex.transport')"
    :options="collect($transports)->mapWithKeys(fn ($transport) => [$transport => $transport])"
    :selected="old('transport', $channel['transport'] ?? 'http')"
    wrapper="w-44" />

<x-atrium::form.select
    name="status"
    :label="__('impex::impex.status')"
    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->value])"
    :selected="old('status', $channel['status'] ?? 'active')"
    wrapper="w-44" />

<x-atrium::form.select
    name="body_policy"
    :label="__('impex::impex.body_policy')"
    :options="collect($policies)->mapWithKeys(fn ($policy) => [$policy->value => __('impex::impex.body_policies.'.$policy->value)])"
    :selected="old('body_policy', $channel['body_policy'] ?? 'all')"
    wrapper="w-56" />

<x-atrium::form.input
    name="signing_secret"
    type="password"
    autocomplete="new-password"
    :label="__('impex::impex.signing_secret')"
    :hint="__('impex::impex.signing_secret_hint')"
    wrapper="w-72" />

<x-atrium::form.textarea
    name="options_json"
    rows="6"
    :label="__('impex::impex.options')"
    :hint="__('impex::impex.options_hint')"
    :value="old('options_json', json_encode($channel['options'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))"
    class="font-mono" />
