<?php

namespace Workbench\App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Flows\ExportOrdersFlow;
use Workbench\App\Flows\ImportCustomersFlow;
use Workbench\App\Flows\PurchaseApprovalFlow;
use Workbench\App\Flows\SupplierStockSyncFlow;
use Workbench\App\Http\Middleware\SignInWorkbenchUser;
use Workbench\App\OpenApi\ResourceResponses;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // The demo flows and channels, registered as an application would in
        // config/impex.php.
        config()->set('impex.flows', [
            'customer-import' => ImportCustomersFlow::class,
            'order-export' => ExportOrdersFlow::class,
            'purchase-approval' => PurchaseApprovalFlow::class,
            'supplier-stock-sync' => SupplierStockSyncFlow::class,
        ]);

        config()->set('impex.schedule', [
            'order-export' => '0 6 * * *',
        ]);

        config()->set('impex.channels', [
            'supplier-feed' => [
                'direction' => 'inbound',
                'signing_secret' => 'workbench-supplier-secret',
                'signature_header' => 'X-Signature',
                'idempotency_header' => 'X-Request-Id',
                'flow' => 'supplier-stock-sync',
                'store_headers' => ['content-type', 'x-request-id'],
            ],
            'storefront-webhooks' => [
                'direction' => 'inbound',
                'signing_secret' => 'workbench-storefront-secret',
                'signature_header' => 'X-Storefront-Signature',
                'store_headers' => ['content-type'],
            ],
            // Deliberately left without a secret: every request to it is
            // recorded and refused.
            'legacy-erp' => [
                'direction' => 'inbound',
                'path' => 'erp/notify',
                'store_headers' => ['content-type'],
            ],
            'crm-api' => ['direction' => 'outbound'],
            'supplier-api' => ['direction' => 'outbound'],
            'partner-sftp' => ['direction' => 'outbound'],
        ]);

        // Drive runs as soon as they start, so runs triggered, signalled,
        // cancelled or retried through the API finish without a queue worker.
        config()->set('queue.default', 'sync');

        // The OpenAPI document (`composer openapi:generate`, committed as
        // openapi.json). Every value is fixed so the export is byte-stable.
        config()->set('scramble.api_path', 'impex');
        config()->set('scramble.info.version', '2.0.0');
        config()->set('scramble.info.description', 'The Impex operator API.');
        config()->set('scramble.ui.title', 'Impex');
        config()->set('scramble.servers', ['Impex' => 'http://localhost/impex']);
        // The workbench user reads the docs at /docs/api in any environment.
        config()->set('scramble.middleware', ['web']);

        // Scramble reads model columns when their tables exist, so a migrated
        // workbench database would change the export. It always reads an
        // empty one instead.
        if ($this->app->runningConsoleCommand('scramble:export')) {
            config()->set('database.connections.openapi', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
            config()->set('database.default', 'openapi');
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Keep the workbench user signed in whatever URL is opened first.
        $this->callAfterResolving(HttpKernel::class, function (HttpKernel $kernel): void {
            if ($kernel instanceof Kernel) {
                $kernel->appendMiddlewareToGroup('web', SignInWorkbenchUser::class);
            }
        });

        // The upstreams the demo flows call are stubs, so their traffic still
        // lands in the ledger without leaving the machine.
        Http::fake([
            'crm.example.test/*' => function (Request $request) {
                /** @var array<int, array{id?: string, email?: string}> $customers */
                $customers = $request['customers'] ?? [];

                foreach ($customers as $row => $customer) {
                    if (! filter_var($customer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                        return Http::response([
                            'message' => sprintf('Row %d (%s): "%s" is not a valid email address.', $row + 1, $customer['id'] ?? '?', $customer['email'] ?? ''),
                        ], 422);
                    }
                }

                return Http::response(['accepted' => count($customers)], 202);
            },
            'supplier.example.test/*' => Http::response(['status' => 'received', 'reference' => 'SUP-88412'], 201),
        ]);

        $this->configureOpenApi();
    }

    /**
     * Document the package's own `impex.*` routes, each under its route name.
     * The per-channel inbound routes (`impex.channels.receive.{channel}`) come
     * from the workbench's channel config, not the package, so they stay out.
     */
    private function configureOpenApi(): void
    {
        Scramble::configure()
            ->routes(fn (Route $route): bool => str_starts_with((string) $route->getName(), 'impex.')
                && ! str_starts_with((string) $route->getName(), 'impex.channels.receive.'))
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                $operation->setOperationId($routeInfo->route->getName());
            })
            ->withOperationTransformers(ResourceResponses::class);
    }
}
