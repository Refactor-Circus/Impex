<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Http;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\CreateChannelTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\ListChannelsTool;
use RefactorCircus\Impex\Domains\Channel\Mcp\Tools\RotateChannelSecretTool;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Channel\Support\StandardWebhooksSigner;
use RefactorCircus\Impex\Domains\Channel\Support\StandardWebhooksValidator;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Impex;
use RefactorCircus\Impex\Tests\Fixtures\Calls;
use RefactorCircus\Impex\Tests\Fixtures\PayloadFlow;

beforeEach(function (): void {
    Calls::reset();
    config()->set('queue.default', 'sync');
    config()->set('impex.outbound.guard', false);
});

it('creates, changes and deletes a stored channel without ever returning its secrets', function (): void {
    $created = $this->postJson('/impex/channels', [
        'name' => 'acme-webhook',
        'direction' => 'outbound',
        'transport' => 'http',
        'options' => ['url' => 'https://acme.test/hooks'],
        'credentials' => ['signing_secret' => 'acme-secret'],
        'body_policy' => 'failures',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'acme-webhook')
        ->assertJsonPath('data.stored', true)
        ->assertJsonPath('data.verifies_signatures', true);

    expect($created->content())->not->toContain('acme-secret');

    $this->getJson('/impex/channels?direction=outbound')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'acme-webhook');

    $this->patchJson('/impex/channels/acme-webhook', ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    $this->deleteJson('/impex/channels/acme-webhook')->assertNoContent();

    expect(ChannelModel::query()->count())->toBe(0);
});

it('refuses a stored channel that would shadow a configured one, or names no transport', function (): void {
    config()->set('impex.channels', ['erp' => ['direction' => 'inbound']]);

    $this->postJson('/impex/channels', ['name' => 'erp', 'direction' => 'inbound', 'transport' => 'http'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'The channel [erp] is defined in config/impex.php. Change it there.');

    $this->postJson('/impex/channels', ['name' => 'pigeon', 'direction' => 'outbound', 'transport' => 'pigeon'])
        ->assertStatus(409);
});

it('rotates a secret, returning it once, and signs with both until the next rotation', function (): void {
    Http::fake();

    ChannelModel::factory()->create([
        'name' => 'acme-webhook',
        'credentials' => ['signing_secret' => 'old-secret'],
    ]);

    $secret = $this->postJson('/impex/channels/acme-webhook/rotate-secret')
        ->assertOk()
        ->json('secret');

    expect($secret)->toStartWith('whsec_');

    app(Impex::class)->send('acme-webhook', ['n' => 1]);

    Http::assertSent(function ($request) use ($secret): bool {
        $signatures = explode(' ', $request->header('webhook-signature')[0]);
        $id = $request->header('webhook-id')[0];
        $timestamp = (int) $request->header('webhook-timestamp')[0];

        return $signatures === [
            'v1,'.StandardWebhooksSigner::signature($secret, $id, $timestamp, '{"n":1}'),
            'v1,'.StandardWebhooksSigner::signature('old-secret', $id, $timestamp, '{"n":1}'),
        ];
    });
});

it('receives on a stored inbound channel verified with Standard Webhooks', function (): void {
    config()->set('impex.flows', ['ingest' => PayloadFlow::class]);

    ChannelModel::factory()->create([
        'name' => 'erp-push',
        'direction' => 'inbound',
        'credentials' => ['signing_secret' => 'erp-secret'],
        'options' => [
            'flow' => 'ingest',
            'signature_validator' => StandardWebhooksValidator::class,
        ],
    ]);

    $body = '{"sku":"ABC-1"}';
    $timestamp = time();
    $signature = StandardWebhooksSigner::signature('erp-secret', 'evt_1', $timestamp, $body);

    $send = fn (int $at, string $sig) => $this->call('POST', '/impex/channels/erp-push', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_WEBHOOK_ID' => 'evt_1',
        'HTTP_WEBHOOK_TIMESTAMP' => (string) $at,
        'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$sig,
    ], content: $body);

    $send($timestamp, $signature)->assertStatus(202);

    // A captured delivery replayed outside the tolerance is refused.
    $stale = $timestamp - 3600;
    $send($stale, StandardWebhooksSigner::signature('erp-secret', 'evt_1', $stale, $body))->assertStatus(403);

    expect(Calls::count('ingest'))->toBe(1);
});

it('does not receive on an outbound channel', function (): void {
    ChannelModel::factory()->create(['name' => 'acme-webhook']);

    $this->postJson('/impex/channels/acme-webhook', ['a' => 1])->assertNotFound();
});

it('scopes run idempotency to the flow', function (): void {
    config()->set('impex.flows', ['a' => PayloadFlow::class, 'b' => PayloadFlow::class]);

    $first = app(Impex::class)->run('a', [['n' => 1]], idempotencyKey: 'shared-key');
    $other = app(Impex::class)->run('b', [['n' => 1]], idempotencyKey: 'shared-key');
    $again = app(Impex::class)->run('a', [['n' => 1]], idempotencyKey: 'shared-key');

    expect($other->getKey())->not->toBe($first->getKey())
        ->and($again->getKey())->toBe($first->getKey())
        ->and(RunModel::query()->count())->toBe(2);
});

it('manages channels over MCP', function (): void {
    mcpTool(CreateChannelTool::class, [
        'name' => 'ops-mail',
        'direction' => 'outbound',
        'transport' => 'mail',
        'options' => ['to' => 'ops@example.test'],
    ])->assertOk()->assertSee('ops-mail');

    mcpTool(ListChannelsTool::class, ['direction' => 'outbound'])->assertOk()->assertSee('ops-mail');

    mcpTool(RotateChannelSecretTool::class, ['channel' => 'ops-mail'])->assertOk()->assertSee('whsec_');
});

it('lets an owner manage only their own channels when authorization is on', function (): void {
    config()->set('impex.authorization', true);

    $owner = User::forceCreate(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'x']);
    $other = User::forceCreate(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'x']);

    $this->actingAs($owner)->postJson('/impex/channels', [
        'name' => 'owner-hook',
        'direction' => 'outbound',
        'transport' => 'http',
        'options' => ['url' => 'https://owner.test/hooks'],
    ])->assertCreated()->assertJsonPath('data.owner_id', (string) $owner->getKey());

    $this->actingAs($other)->patchJson('/impex/channels/owner-hook', ['status' => 'disabled'])->assertForbidden();
    $this->actingAs($other)->getJson('/impex/channels')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($owner)->getJson('/impex/channels')->assertOk()->assertJsonCount(1, 'data');
});
