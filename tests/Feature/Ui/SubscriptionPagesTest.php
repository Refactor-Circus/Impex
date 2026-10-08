<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use JayI\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use JayI\Impex\Domains\Subscription\Enums\SubscriptionStatus;
use JayI\Impex\Domains\Subscription\Models\SubscriberModel;
use JayI\Impex\Domains\Subscription\Models\SubscriptionModel;
use JayI\Impex\Tests\Fixtures\FakeCatalog;
use JayI\Impex\Tests\Fixtures\ProductStreamFixture;

beforeEach(function (): void {
    ValidateCsrfToken::except(['*']);
    app()->detectEnvironment(fn (): string => 'local');

    FakeCatalog::reset();
    Queue::fake();
    config()->set('impex.streams', [ProductStreamFixture::class]);
    config()->set('impex.outbound.guard', false);
});

function pushedSubscription(): SubscriptionModel
{
    return app(CreateSubscriptionAction::class)->execute(
        SubscriberModel::factory()->create(['name' => 'Acme Supply']),
        ['stream' => 'fixture.products', 'topics' => ['pricing'], 'endpoint' => ['url' => 'https://acme.test/hooks']],
    )['subscription'];
}

it('lists subscriptions with their subscriber and endpoint', function (): void {
    pushedSubscription();

    $this->get(route('atrium.impex.subscriptions.index'))
        ->assertOk()
        ->assertSee('fixture.products')
        ->assertSee('Acme Supply')
        ->assertSee('https://acme.test/hooks');
});

it('shows a subscription and pauses, resumes, replays and pings it', function (): void {
    Http::fake(['acme.test/*' => Http::response()]);
    $subscription = pushedSubscription();

    $this->get(route('atrium.impex.subscriptions.show', $subscription))
        ->assertOk()
        ->assertSee('pricing')
        ->assertSee(__('impex::impex.no_deliveries'));

    $this->post(route('atrium.impex.subscriptions.pause', $subscription))->assertRedirect();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Paused);

    $this->post(route('atrium.impex.subscriptions.resume', $subscription))->assertRedirect();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);

    $this->post(route('atrium.impex.subscriptions.replay', $subscription), ['replay_from' => 3])->assertRedirect();
    expect($subscription->refresh()->cursor)->toBe(2);

    $this->post(route('atrium.impex.subscriptions.ping', $subscription))
        ->assertRedirect()
        ->assertSessionHas('status', __('impex::impex.ping_succeeded'));
});
