<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Impex\Support\Tables;
use RefactorCircus\Keystone\Testing\Arch;

const IMPEX_DOMAINS = __DIR__.'/../src/Domains';

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('RefactorCircus\Impex')
    ->toUseStrictTypes();

arch('actions are final classes')
    ->expect(Arch::domainNamespaces('RefactorCircus\Impex', IMPEX_DOMAINS, 'Actions'))
    ->classes()
    ->toBeFinal();

arch('actions take their input as Data objects, not arrays')
    ->expect(fn (): array => array_values(array_filter(
        array_map(function (string $action): ?string {
            foreach ((new ReflectionMethod($action, 'execute'))->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && $type->getName() === 'array' && in_array($parameter->getName(), ['data', 'filters', 'input', 'attributes', 'values', 'validated'], true)) {
                    return $action.'::execute($'.$parameter->getName().')';
                }
            }

            return null;
        }, Arch::domainClasses('RefactorCircus\Impex', IMPEX_DOMAINS, 'Actions')),
    )))
    ->toBeEmpty();

arch('action input validates itself')
    ->expect(fn (): array => Arch::dataWithoutRules(Arch::domainClasses('RefactorCircus\Impex', IMPEX_DOMAINS, 'Data')))
    ->toBeEmpty();

arch('every action dispatches a starting and a finished event')
    ->expect(fn (): array => Arch::actionEventGaps(IMPEX_DOMAINS.'/*/Actions/*.php'))
    ->toBeEmpty();

arch('actions write inside a transaction')
    ->expect(fn (): array => Arch::writesOutsideTransactions(IMPEX_DOMAINS.'/*/Actions/*.php'))
    ->toBeEmpty();

arch('every model fires an event for every lifecycle hook')
    ->expect(fn (): array => Arch::modelEventGaps(Arch::domainClasses('RefactorCircus\Impex', IMPEX_DOMAINS, 'Models')))
    ->toBeEmpty();

arch('requests validate once, with the Data rules, and build from what they validated')
    ->expect(fn (): array => array_values(array_filter(
        [...(array) glob(IMPEX_DOMAINS.'/*/Http/Requests/*.php'), ...(array) glob(IMPEX_DOMAINS.'/*/Mcp/Requests/*.php')],
        fn (string $path): bool => str_contains((string) file_get_contents($path), 'Data::from('),
    )))
    ->toBeEmpty();

arch('models are final and named for their entity')
    ->expect(Arch::domainNamespaces('RefactorCircus\Impex', IMPEX_DOMAINS, 'Models'))
    ->classes()
    ->toBeFinal()
    ->toHaveSuffix('Model');

arch('jobs carry identifiers only, never payloads')
    ->expect('RefactorCircus\Impex\Jobs')
    ->toOnlyUse([
        'Illuminate\Bus\Queueable',
        'Illuminate\Contracts\Queue\Interruptible',
        'Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing',
        'Illuminate\Contracts\Queue\ShouldQueue',
        'Illuminate\Foundation\Bus\Dispatchable',
        'Illuminate\Queue\InteractsWithQueue',
        'Illuminate\Queue\SerializesModels',
        'RefactorCircus\Impex\Domains\Batch\Services\BatchRunner',
        'RefactorCircus\Impex\Domains\Run\Services\Engine',
        'RefactorCircus\Impex\Domains\Subscription\Services\Detector',
        'RefactorCircus\Impex\Domains\Subscription\Services\Dispatcher',
        'RefactorCircus\Impex\Domains\Subscription\Services\Exporter',
        'RefactorCircus\Impex\Domains\Subscription\Services\SubscriptionJobs',
        'RefactorCircus\Impex\Support\Concerns\UsesConfiguredMiddleware',
        'RefactorCircus\Impex\Support\JobMiddleware',
    ]);

// Parity is the default, with declared exceptions. An Action missing from
// the HTTP API or MCP is a test failure unless it is listed in
// MCP_EXCEPTIONS (tests/Pest.php) with a reason.
arch('every use case is reachable from both the HTTP API and MCP')
    ->expect(fn (): array => parityGaps())
    ->toBeEmpty();

arch('every table name comes from Tables, never a literal')
    ->expect(function (): array {
        $files = [dirname(__DIR__).'/database/migrations/*.php'];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php' && ! str_ends_with($file->getPathname(), 'Support/Tables.php')) {
                $files[] = $file->getPathname();
            }
        }

        return Arch::literalTableNames($files, array_values(Tables::DEFAULTS));
    })
    ->toBeEmpty();

arch('every model has a policy in the config')
    ->expect(fn (): array => Arch::modelsWithoutPolicies(
        array_values(array_filter(
            Arch::domainClasses('RefactorCircus\Impex', IMPEX_DOMAINS, 'Models'),
            fn (string $class): bool => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract(),
        )),
        (require dirname(__DIR__).'/config/impex.php')['policies'],
    ))
    ->toBeEmpty();
