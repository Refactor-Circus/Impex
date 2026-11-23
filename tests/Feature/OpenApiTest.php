<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Process;
use RefactorCircus\Impex\Domains\Channel\Resources\ChannelResource;
use RefactorCircus\Impex\Domains\Flow\Mcp\Tools\ListFlowsTool;
use RefactorCircus\Impex\Domains\Message\Resources\MessageResource;
use RefactorCircus\Impex\Domains\Run\Http\Resources\RunStatsResource;
use RefactorCircus\Impex\Domains\Run\Http\Resources\SettingsSummaryResource;
use RefactorCircus\Impex\Domains\Run\Resources\RunResource;
use RefactorCircus\Impex\Domains\Subscription\Resources\SubscriptionResource;
use Symfony\Component\Finder\Finder;
use Workbench\App\OpenApi\ResourceResponses;

/*
 * openapi.json describes the operator API. Scramble exports it from the
 * workbench (`composer openapi:generate`); it is committed, so a stale copy
 * fails here (criteria 15, 16 and 20).
 */

function openApiRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array<string, mixed>
 */
function openApiDocument(): array
{
    /** @var array<string, mixed> $document */
    $document = json_decode((string) file_get_contents(openApiRoot().'/openapi.json'), true, flags: JSON_THROW_ON_ERROR);

    return $document;
}

/**
 * The operation whose operationId is the route name.
 *
 * @return array<string, mixed>|null
 */
function openApiOperation(string $routeName): ?array
{
    /** @var array<string, array<string, mixed>> $paths */
    $paths = openApiDocument()['paths'] ?? [];

    foreach ($paths as $operations) {
        foreach ($operations as $operation) {
            if (is_array($operation) && ($operation['operationId'] ?? null) === $routeName) {
                return $operation;
            }
        }
    }

    return null;
}

/**
 * A schema with its local `$ref` followed.
 *
 * @param  array<string, mixed>  $schema
 * @return array<string, mixed>
 */
function openApiResolve(array $schema): array
{
    while (isset($schema['$ref']) && is_string($schema['$ref'])) {
        $target = openApiDocument();

        foreach (explode('/', substr($schema['$ref'], 2)) as $segment) {
            /** @var array<string, mixed> $target */
            $target = $target[$segment];
        }

        $schema = $target;
    }

    return $schema;
}

/**
 * The fields of the success response's `data`: the object itself, or each
 * item of a list.
 *
 * @return list<string>
 */
function openApiDataFields(string $routeName): array
{
    $operation = openApiOperation($routeName);

    expect($operation)->not->toBeNull();

    /** @var array<string, mixed> $response */
    $response = openApiResolve($operation['responses']['200'] ?? []);
    $body = openApiResolve($response['content']['application/json']['schema'] ?? []);

    expect($body['type'] ?? null)->toBe('object');

    $data = openApiResolve($body['properties']['data'] ?? []);

    if (($data['type'] ?? null) === 'array') {
        $data = openApiResolve($data['items'] ?? []);
    }

    expect($data['type'] ?? null)->toBe('object');

    return array_keys($data['properties'] ?? []);
}

it('is an OpenAPI 3.1 document for Impex 2.0.0', function (): void {
    expect(openApiDocument()['openapi'])->toStartWith('3.1.')
        ->and(openApiDocument()['info']['version'])->toBe('2.0.0');
});

it('matches a fresh export from the workbench', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'impex-openapi-');

    try {
        $result = Process::path(openApiRoot())
            ->timeout(300)
            ->run([PHP_BINARY, 'vendor/bin/testbench', 'scramble:export', '--path='.$path]);

        expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

        $normalize = fn (string $json): string => rtrim(str_replace("\r\n", "\n", $json));

        expect($normalize((string) file_get_contents($path)))
            ->toBe($normalize((string) file_get_contents(openApiRoot().'/openapi.json')), 'openapi.json is stale: run composer openapi:generate.');
    } finally {
        @unlink($path);
    }
});

it('has an operation named after every impex route under the prefix', function (): void {
    $prefix = config('impex.routes.prefix').'/';

    $names = [];

    foreach (app('router')->getRoutes() as $route) {
        /** @var Route $route */
        $name = $route->getName();

        if ($name !== null && str_starts_with($name, 'impex.') && str_starts_with($route->uri(), $prefix)) {
            $names[] = $name;
        }
    }

    expect($names)->toContain('impex.stats.show', 'impex.search.index', 'impex.settings-summary.show');

    $missing = array_values(array_filter($names, fn (string $name): bool => openApiOperation($name) === null));

    expect($missing)->toBe([]);
});

it('describes the success response with the fields the resource declares', function (string $routeName, Closure $fields): void {
    /** @var list<string> $expected */
    $expected = $fields(new JsonSchemaTypeFactory);

    expect($expected)->not->toBe([])
        ->and(openApiDataFields($routeName))->toContain(...$expected);
})->with([
    'stats' => ['impex.stats.show', fn (JsonSchemaTypeFactory $schema): array => array_keys(RunStatsResource::schema($schema))],
    'search' => ['impex.search.index', fn (JsonSchemaTypeFactory $schema): array => array_keys(RunResource::schema($schema))],
    'settings summary' => ['impex.settings-summary.show', fn (JsonSchemaTypeFactory $schema): array => array_keys(SettingsSummaryResource::schema($schema))],
    'runs' => ['impex.runs.index', fn (JsonSchemaTypeFactory $schema): array => array_keys(RunResource::schema($schema))],
    'run' => ['impex.runs.show', fn (JsonSchemaTypeFactory $schema): array => array_keys(RunResource::schema($schema))],
    'messages' => ['impex.messages.index', fn (JsonSchemaTypeFactory $schema): array => array_keys(MessageResource::schema($schema))],
    // The flow list has no resource class: its MCP tool declares the items.
    'flows' => ['impex.flows.index', fn (JsonSchemaTypeFactory $schema): array => array_keys(
        JsonSchema::object(fn (JsonSchemaTypeFactory $schema): array => app(ListFlowsTool::class)->outputSchema($schema))
            ->toArray()['properties']['data']['items']['properties'],
    )],
    'channels' => ['impex.channels.index', fn (JsonSchemaTypeFactory $schema): array => array_keys(ChannelResource::schema($schema))],
    'subscriptions' => ['impex.subscriptions.index', fn (JsonSchemaTypeFactory $schema): array => array_keys(SubscriptionResource::schema($schema))],
]);

it('writes the declared response for every operation it promises', function (): void {
    expect(array_keys(ResourceResponses::responses(new JsonSchemaTypeFactory)))->toEqualCanonicalizing([
        'impex.stats.show',
        'impex.search.index',
        'impex.settings-summary.show',
        'impex.runs.index',
        'impex.runs.show',
        'impex.messages.index',
        'impex.flows.index',
        'impex.channels.index',
        'impex.subscriptions.index',
    ]);
});

it('keeps scramble out of the shipped source and config', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in([openApiRoot().'/src', openApiRoot().'/config']) as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        if (str_contains($contents, 'Dedoc\\Scramble') || str_contains($contents, 'Dedoc\\\\Scramble')) {
            $offenders[] = substr($file->getPathname(), strlen(openApiRoot()) + 1);
        }
    }

    expect($offenders)->toBe([]);
});
