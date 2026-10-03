<?php

namespace Workbench\App\Providers;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Flows\ExportOrdersFlow;
use Workbench\App\Flows\ImportCustomersFlow;
use Workbench\App\Flows\PurchaseApprovalFlow;
use Workbench\App\Flows\SupplierStockSyncFlow;

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
            // Deliberately left without a secret: the Channels page flags it,
            // and every request to it is recorded and refused.
            'legacy-erp' => [
                'direction' => 'inbound',
                'path' => 'erp/notify',
                'store_headers' => ['content-type'],
            ],
            'crm-api' => ['direction' => 'outbound'],
            'supplier-api' => ['direction' => 'outbound'],
            'partner-sftp' => ['direction' => 'outbound'],
        ]);

        // Drive runs as soon as they start, so the Run, Signal, Cancel and
        // Retry buttons work without a queue worker.
        config()->set('queue.default', 'sync');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // The workbench dashboard is open so `composer serve` is usable
        // without logging in. A real application defines a real gate.
        Gate::define('viewAtrium', fn ($user = null): bool => true);

        // Show every run on the dashboard, including scheduled and channel
        // runs nobody owns, as an operator dashboard would.
        config()->set('impex.atrium.show_all', true);

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
    }
}
