<?php

declare(strict_types=1);

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use RefactorCircus\Atrium\Domains\Screens\Data\ResolvedScreen;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Data\TabDefinition;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Impex\Atrium\Screens;
use RefactorCircus\Impex\Domains\Channel\Models\ChannelModel;
use RefactorCircus\Impex\Domains\Flow\Models\FlowOverrideModel;
use RefactorCircus\Impex\Domains\Message\Enums\Direction;
use RefactorCircus\Impex\Domains\Message\Models\MessageModel;
use RefactorCircus\Impex\Domains\Run\Enums\RunStatus;
use RefactorCircus\Impex\Domains\Run\Enums\RunTrigger;
use RefactorCircus\Impex\Domains\Run\Models\RunModel;
use RefactorCircus\Impex\Domains\Subscription\Actions\CreateSubscriptionAction;
use RefactorCircus\Impex\Domains\Subscription\Data\CreateSubscriptionData;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriberModel;
use RefactorCircus\Impex\Domains\Subscription\Models\SubscriptionModel;
use RefactorCircus\Impex\Tests\Fixtures\FakeCatalog;
use RefactorCircus\Impex\Tests\Fixtures\LinearFlow;
use RefactorCircus\Impex\Tests\Fixtures\ProductStreamFixture;
use RefactorCircus\Impex\Tests\Fixtures\SignalFlow;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/*
 * Every Impex page with a controller view declares itself with
 * Screen::standard() and resolves it, so a package can add a tab or a section
 * to any standard slot (criterion 7). Each dataset row names the screen, its
 * kind and how many rows its fixture lists.
 */

beforeEach(function (): void {
    ValidateCsrfToken::except(['*']);

    // Atrium denies access outside local until a gate is defined.
    app()->detectEnvironment(fn (): string => 'local');

    FakeCatalog::reset();
    Queue::fake();

    config()->set('impex.flows', ['linear' => LinearFlow::class, 'signal' => SignalFlow::class]);
    config()->set('impex.streams', [ProductStreamFixture::class]);
    config()->set('impex.outbound.guard', false);
    config()->set('impex.channels', ['erp' => ['direction' => 'inbound', 'flow' => 'linear']]);

    View::replaceNamespace('workbench', dirname(__DIR__, 3).'/workbench/resources/views');
});

dataset('impex screens', [
    'runs' => ['impex.runs', Screen::LIST, 1],
    'run' => ['impex.run', Screen::DETAIL, 0],
    'messages' => ['impex.messages', Screen::LIST, 1],
    'message' => ['impex.message', Screen::DETAIL, 0],
    // linear has a stored override, signal has none.
    'flows' => ['impex.flows', Screen::LIST, 2],
    // carrier-api is stored, erp comes from config.
    'channels' => ['impex.channels', Screen::LIST, 2],
    'channel-create' => ['impex.channel-create', Screen::DETAIL, 0],
    'channel' => ['impex.channel', Screen::DETAIL, 0],
    'subscriptions' => ['impex.subscriptions', Screen::LIST, 1],
    'subscription' => ['impex.subscription', Screen::DETAIL, 0],
]);

function declaredScreenRun(): RunModel
{
    return RunModel::query()->create([
        'flow' => 'linear',
        'flow_class' => LinearFlow::class,
        'status' => RunStatus::Running,
        'trigger' => RunTrigger::Api,
    ]);
}

function declaredScreenMessage(): MessageModel
{
    return MessageModel::query()->create([
        'direction' => Direction::Inbound,
        'channel' => 'webhook',
        'endpoint' => '/hooks/example',
        'transport' => 'http',
        'bytes' => 42,
        'occurred_at' => now(),
    ]);
}

function declaredScreenSubscription(): SubscriptionModel
{
    return app(CreateSubscriptionAction::class)->execute(
        SubscriberModel::factory()->create(['name' => 'Acme Supply']),
        new CreateSubscriptionData(stream: 'fixture.products', topics: ['pricing'], endpoint: ['url' => 'https://acme.test/hooks']),
    )['subscription'];
}

/**
 * The screen's URL, after creating what it shows: a record for a detail
 * screen, at least one row for a list.
 */
function declaredScreenUrl(string $key): string
{
    $fixtures = [
        'impex.runs' => function (): string {
            declaredScreenRun();

            return route('atrium.impex.runs.index');
        },
        'impex.run' => fn (): string => route('atrium.impex.runs.show', declaredScreenRun()),
        'impex.messages' => function (): string {
            declaredScreenMessage();

            return route('atrium.impex.messages.index');
        },
        'impex.message' => fn (): string => route('atrium.impex.messages.show', declaredScreenMessage()),
        'impex.flows' => function (): string {
            FlowOverrideModel::query()->create(['slug' => 'linear', 'enabled' => true]);

            return route('atrium.impex.flows.index');
        },
        'impex.channels' => function (): string {
            ChannelModel::factory()->create(['name' => 'carrier-api']);

            return route('atrium.impex.channels.index');
        },
        'impex.channel-create' => fn (): string => route('atrium.impex.channels.create'),
        'impex.channel' => function (): string {
            ChannelModel::factory()->create(['name' => 'carrier-api']);

            return route('atrium.impex.channels.show', 'carrier-api');
        },
        'impex.subscriptions' => function (): string {
            declaredScreenSubscription();

            return route('atrium.impex.subscriptions.index');
        },
        'impex.subscription' => fn (): string => route('atrium.impex.subscriptions.show', declaredScreenSubscription()),
    ];

    return $fixtures[$key]();
}

/**
 * The standard slots a screen of this kind declares.
 *
 * @return list<string>
 */
function declaredScreenSlots(string $kind): array
{
    return ['header.actions', 'before', 'after', $kind === Screen::DETAIL ? 'aside' : 'row.actions'];
}

function declaredScreenSection(string $key, ?Closure $resolve = null): SectionDefinition
{
    return SectionDefinition::make($key)
        ->view('workbench::test-host-marker')
        ->resolve($resolve ?? fn (): array => ['marker' => $key])
        ->public();
}

function declaredScreenDocument(string $html): HTMLDocument
{
    return HTMLDocument::createFromString($html, LIBXML_NOERROR);
}

it('declares itself as a standard screen with only the overview tab', function (string $key, string $kind): void {
    $resolved = $this->get(declaredScreenUrl($key))->assertOk()->viewData('resolved');

    expect($resolved)->toBeInstanceOf(ResolvedScreen::class)
        ->and($resolved->screen->key)->toBe($key)
        ->and($resolved->screen->kind)->toBe($kind)
        ->and($resolved->screen->slots)->toBe($kind === Screen::DETAIL ? ['header.actions', 'before', 'after', 'aside'] : ['header.actions', 'before', 'after'])
        ->and($resolved->screen->rowSlots)->toBe($kind === Screen::DETAIL ? [] : ['row.actions'])
        ->and(array_map(fn (TabDefinition $tab): string => $tab->key, $resolved->screen->tabs))->toBe([Screen::OVERVIEW]);
})->with('impex screens');

it('renders no extension markup with nothing registered', function (string $key): void {
    $this->get(declaredScreenUrl($key))
        ->assertOk()
        ->assertSee('data-testid="impex-content"', false)
        ->assertDontSee('data-testid="section-', false)
        ->assertDontSee('data-testid="screen-tabs"', false)
        ->assertDontSee('data-testid="screen-aside"', false)
        ->assertDontSee('data-testid="tab-panel-', false);
})->with('impex screens');

it('renders a section in every standard slot, each in its own region only', function (string $key, string $kind, int $rows): void {
    $slots = declaredScreenSlots($kind);

    foreach ($slots as $slot) {
        Atrium::screens()->section($key, $slot, declaredScreenSection('test.'.$slot));
    }

    $html = (string) $this->get(declaredScreenUrl($key))->assertOk()->getContent();
    $document = declaredScreenDocument($html);

    $content = $document->querySelector('[data-testid="impex-content"]');
    $header = $document->querySelector('#atrium-main h1')?->parentElement?->parentElement;

    expect($content)->not->toBeNull()
        ->and($header)->not->toBeNull();

    // Every marker renders once per place it belongs, and nowhere else.
    foreach ($slots as $slot) {
        expect(substr_count($html, 'marker test.'.$slot))->toBe($slot === 'row.actions' ? $rows : 1, "[{$slot}] on [{$key}]");
    }

    $wrapper = fn (string $slot): Element => $document->querySelector('[data-testid="section-test.'.$slot.'"]')
        ?? throw new RuntimeException("No [{$slot}] section on [{$key}].");

    $headerActions = $wrapper('header.actions');
    expect($header->contains($headerActions))->toBeTrue()
        ->and($content->contains($headerActions))->toBeFalse();

    $before = $wrapper('before');
    expect($header->contains($before) || $content->contains($before))->toBeFalse()
        ->and($before->compareDocumentPosition($content) & Node::DOCUMENT_POSITION_FOLLOWING)->not->toBe(0);

    $after = $wrapper('after');
    expect($header->contains($after) || $content->contains($after))->toBeFalse()
        ->and($content->compareDocumentPosition($after) & Node::DOCUMENT_POSITION_FOLLOWING)->not->toBe(0);

    if ($kind === Screen::DETAIL) {
        $aside = $document->querySelector('[data-testid="screen-aside"]');

        expect($aside)->not->toBeNull()
            ->and($aside->contains($wrapper('aside')))->toBeTrue()
            ->and($content->contains($aside))->toBeFalse()
            ->and($aside->contains($content))->toBeFalse();

        return;
    }

    $tableRows = iterator_to_array($content->querySelectorAll('tbody > tr'));

    expect($tableRows)->toHaveCount($rows);

    foreach ($tableRows as $row) {
        expect($row->lastElementChild?->querySelectorAll('[data-testid="section-test.row.actions"]'))->toHaveCount(1);
    }
})->with('impex screens');

it('never renders or resolves a section in the standard slot it does not declare', function (string $key, string $kind): void {
    $undeclared = $kind === Screen::DETAIL ? 'row.actions' : 'aside';
    $resolved = 0;

    Atrium::screens()->section($key, $undeclared, declaredScreenSection('test.undeclared', function () use (&$resolved): array {
        $resolved++;

        return ['marker' => 'test.undeclared'];
    }));

    $response = $this->get(declaredScreenUrl($key))->assertOk();

    $response->assertDontSee('marker test.undeclared')->assertDontSee('data-testid="screen-aside"', false);

    $screen = $response->viewData('resolved');

    // Asking for the other kind's slot is refused outright.
    expect($resolved)->toBe(0)
        ->and(fn (): array => $kind === Screen::DETAIL
            ? $screen->rowSections('aside', new RunModel)
            : $screen->sections('row.actions'))
        ->toThrow(InvalidArgumentException::class);
})->with('impex screens');

it('shows a registered tab whose panel, opened through ?tab=, replaces the page content', function (string $key): void {
    Atrium::screens()
        ->tab($key, TabDefinition::make('test.tab')
            ->view('workbench::test-host-marker')
            ->resolve(fn (): array => ['marker' => 'test.tab'])
            ->public())
        ->section($key, 'header.actions', declaredScreenSection('test.header.actions'))
        ->section($key, 'before', declaredScreenSection('test.before'));

    $url = declaredScreenUrl($key);

    $this->get($url)
        ->assertOk()
        ->assertSee('data-testid="tab-test.tab"', false)
        ->assertSee('data-testid="impex-content"', false)
        ->assertSee('marker test.before')
        ->assertDontSee('marker test.tab');

    $this->get($url.(str_contains($url, '?') ? '&' : '?').'tab=test.tab')
        ->assertOk()
        ->assertSee('data-testid="tab-panel-test.tab"', false)
        ->assertSee('marker test.tab')
        ->assertSee('marker test.header.actions')
        ->assertDontSee('marker test.before')
        ->assertDontSee('data-testid="impex-content"', false);
})->with('impex screens');

it('gives stored flows and channels their model and the rest an unsaved stand-in', function (): void {
    $override = FlowOverrideModel::query()->create(['slug' => 'linear', 'enabled' => true]);
    $channel = ChannelModel::factory()->create(['name' => 'carrier-api']);

    Atrium::screens()
        ->section('impex.flows', 'row.actions', declaredScreenSection('test.flow', fn (FlowOverrideModel $row): array => [
            'marker' => $row->slug.($row->exists ? ' stored' : ' stand-in'),
        ]))
        ->section('impex.channels', 'row.actions', declaredScreenSection('test.channel', fn (ChannelModel $row): array => [
            'marker' => $row->name.($row->exists ? ' stored' : ' stand-in'),
        ]));

    DB::enableQueryLog();

    $this->get(route('atrium.impex.flows.index'))
        ->assertOk()
        ->assertSee('marker linear stored for '.$override->getKey())
        ->assertSee('marker signal stand-in');

    // The rows come from one keyed query for every listed flow.
    expect(declaredScreenKeyedQueries($override->getTable(), 'slug'))->toBe(1);

    DB::flushQueryLog();

    $this->get(route('atrium.impex.channels.index'))
        ->assertOk()
        ->assertSee('marker carrier-api stored for '.$channel->getKey())
        ->assertSee('marker erp stand-in');

    expect(declaredScreenKeyedQueries($channel->getTable(), 'name'))->toBe(1);
});

/**
 * How many logged queries fetched rows of the table by a list of keys.
 */
function declaredScreenKeyedQueries(string $table, string $column): int
{
    return collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], '"'.$table.'"') && str_contains($query['query'], '"'.$column.'" in ('))
        ->count();
}

it('renders a stand-in row without firing model events or issuing queries', function (Model $row): void {
    $key = $row instanceof FlowOverrideModel ? 'impex.flows' : 'impex.channels';

    Atrium::screens()->section($key, 'row.actions', SectionDefinition::make('test.stand-in')
        ->view('workbench::test-host-marker')
        ->resolve(fn (): array => ['marker' => 'stand-in'])
        ->permission('view'));

    $request = Request::create('/');
    $resolved = app(ScreenExtensions::class)->resolve(Screens::declare($key, Screen::LIST, null, $row::class), $request);

    $events = [];

    Event::listen('*', function (string $name, array $payload) use (&$events): void {
        if (str_starts_with($name, 'eloquent.') || ($payload[0] ?? null) instanceof ModelLifecycleEvent) {
            $events[] = $name;
        }
    });

    DB::flushQueryLog();
    DB::enableQueryLog();

    $html = Blade::render('<x-atrium::screen-sections :screen="$resolved" slot="row.actions" :row="$row" />', [
        'resolved' => $resolved,
        'row' => $row,
    ]);

    expect($html)->toContain('marker stand-in')
        ->and($row->exists)->toBeFalse()
        ->and(DB::getQueryLog())->toBe([])
        ->and($events)->toBe([]);
})->with([
    'flow' => fn (): Model => new FlowOverrideModel(['slug' => 'signal']),
    'channel' => fn (): Model => new ChannelModel(['name' => 'erp']),
]);
