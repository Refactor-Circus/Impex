<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use JayI\Impex\Domains\Channel\Enums\BodyPolicy;
use JayI\Impex\Domains\Channel\Enums\ChannelStatus;
use JayI\Impex\Domains\Channel\Models\ChannelModel;

beforeEach(function (): void {
    ValidateCsrfToken::except(['*']);
    app()->detectEnvironment(fn (): string => 'local');
    config()->set('impex.outbound.guard', false);
});

it('creates an outbound channel from the dashboard', function (): void {
    $this->get(route('atrium.impex.channels.create'))->assertOk();

    $this->post(route('atrium.impex.channels.store'), [
        'name' => 'carrier-api',
        'direction' => 'outbound',
        'transport' => 'http',
        'status' => 'active',
        'body_policy' => 'failures',
        'signing_secret' => 'carrier-secret',
        'options_json' => '{"url": "https://api.carrier.test/rates"}',
    ])->assertRedirect(route('atrium.impex.channels.show', 'carrier-api'));

    $channel = ChannelModel::query()->where('name', 'carrier-api')->sole();

    expect($channel->options)->toBe(['url' => 'https://api.carrier.test/rates'])
        ->and($channel->credentials)->toBe(['signing_secret' => 'carrier-secret'])
        ->and($channel->body_policy)->toBe(BodyPolicy::Failures);

    $this->get(route('atrium.impex.channels.show', 'carrier-api'))
        ->assertOk()
        ->assertSee('api.carrier.test')
        // The secret is never rendered back.
        ->assertDontSee('carrier-secret');
});

it('refuses options that are not JSON', function (): void {
    $this->post(route('atrium.impex.channels.store'), [
        'name' => 'broken',
        'direction' => 'outbound',
        'transport' => 'http',
        'options_json' => '{nope',
    ])->assertSessionHasErrors('options_json');
});

it('changes a channel, keeping its secret when the field is left blank', function (): void {
    ChannelModel::factory()->create(['name' => 'carrier-api', 'credentials' => ['signing_secret' => 'keep-me']]);

    $this->patch(route('atrium.impex.channels.update', 'carrier-api'), [
        'transport' => 'http',
        'status' => 'disabled',
        'body_policy' => 'none',
        'signing_secret' => '',
        'options_json' => '{"url": "https://new.carrier.test"}',
    ])->assertRedirect();

    $channel = ChannelModel::query()->where('name', 'carrier-api')->sole();

    expect($channel->status)->toBe(ChannelStatus::Disabled)
        ->and($channel->options)->toBe(['url' => 'https://new.carrier.test'])
        ->and($channel->credentials)->toBe(['signing_secret' => 'keep-me']);
});

it('rotates a secret, showing the new one once, and deletes a channel', function (): void {
    ChannelModel::factory()->create(['name' => 'carrier-api', 'credentials' => ['signing_secret' => 'old']]);

    $this->post(route('atrium.impex.channels.rotate-secret', 'carrier-api'))
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'whsec_'));

    $this->delete(route('atrium.impex.channels.destroy', 'carrier-api'))->assertRedirect(route('atrium.impex.channels.index'));

    expect(ChannelModel::query()->count())->toBe(0);
});

it('shows a configured channel read-only', function (): void {
    config()->set('impex.channels', ['erp' => ['direction' => 'inbound', 'signing_secret' => 'shhh']]);

    $this->get(route('atrium.impex.channels.show', 'erp'))
        ->assertOk()
        ->assertSee(__('impex::impex.configured_channel_hint'))
        ->assertDontSee('data-testid="update-channel"', false);
});
