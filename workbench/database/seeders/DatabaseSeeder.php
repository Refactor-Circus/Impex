<?php

namespace Workbench\Database\Seeders;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use JayI\Impex\Domains\Flow\Actions\RunFlowAction;
use JayI\Impex\Domains\Run\Actions\AttachRunOwnerAction;
use JayI\Impex\Domains\Run\Actions\CancelRunAction;
use JayI\Impex\Domains\Run\Enums\RunStatus;
use JayI\Impex\Domains\Run\Enums\StepStatus;
use JayI\Impex\Domains\Run\Models\RunModel;
use JayI\Impex\Domains\Run\Models\RunStepModel;
use JayI\Impex\Domains\Run\Services\Engine;
use JayI\Impex\Domains\Signal\Actions\SignalRunAction;
use Workbench\Database\Factories\UserFactory;

/**
 * Demo data, built through Impex's own Actions, engine and channels.
 *
 * Runs are started the way the dashboard, the scheduler and an upstream
 * webhook start them, spread over the last two days. The workbench queue is
 * sync, so most runs finish (or park on a signal) as they start. The runs
 * shown mid-flight are started with their jobs held, then driven one step at a
 * time through the engine, as a worker would.
 */
class DatabaseSeeder extends Seeder
{
    private const string HELD = 'workbench-held';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $test = UserFactory::new()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $ada = UserFactory::new()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
        $grace = UserFactory::new()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

        $now = Carbon::now()->startOfMinute();
        $at = fn (string $ago) => Carbon::setTestNow($now->copy()->sub($ago));

        try {
            // Nobody answers this one: impex:tick times its wait out below.
            $at('3 days 5 hours');
            $this->start('purchase-approval', ['PO-1035', 'Hooli', 980], $grace);

            // Nightly exports, started by the scheduler and owned by nobody.
            $at('2 days 3 hours');
            $this->scheduled('order-export');

            // A clean import, shared with a colleague.
            $at('2 days');
            $september = $this->start('customer-import', ['customers-2026-09.csv'], $ada);
            app(AttachRunOwnerAction::class)->execute($september, [
                'owner_type' => $grace->getMorphClass(),
                'owner_id' => (string) $grace->getKey(),
                'role' => 'viewer',
            ]);

            // The CRM refuses a malformed address: the import is undone and
            // the run fails.
            $at('1 day 20 hours');
            $this->start('customer-import', ['customers-legacy-crm.csv'], $test);

            // Approved a few hours after it was raised.
            $at('1 day 6 hours');
            $approved = $this->start('purchase-approval', ['PO-1038', 'Acme Fasteners', 12900], $grace);
            $at('1 day 2 hours');
            $this->signal($approved, ['approved' => true, 'by' => 'ada@example.com']);

            $at('1 day 3 hours');
            $this->scheduled('order-export');

            // Turned down.
            $at('1 day 1 hour');
            $rejected = $this->start('purchase-approval', ['PO-1039', 'Initech', 2300], $test);
            $at('22 hours');
            $this->signal($rejected, ['approved' => false, 'by' => 'test@example.com', 'reason' => 'Over the Q4 budget.']);

            // Withdrawn while it waited.
            $at('20 hours');
            $withdrawn = $this->start('purchase-approval', ['PO-1041', 'Globex', 7650], $ada);
            $at('19 hours');
            app(CancelRunAction::class)->execute($withdrawn, ['reason' => 'The supplier withdrew the quote.']);

            // Upstream traffic: a signed supplier feed starts a run, a forged
            // copy is refused, a storefront webhook is only recorded, and the
            // unsigned legacy ERP channel refuses everything.
            $at('9 hours');
            $feed = [
                'supplier' => 'Northwind Traders',
                'items' => [
                    ['sku' => 'NW-HINGE-4', 'quantity' => 420],
                    ['sku' => 'NW-LATCH-2', 'quantity' => 6],
                    ['sku' => 'NW-BOLT-10', 'quantity' => 1800],
                    ['sku' => 'NW-STRIKE-1', 'quantity' => 3],
                ],
            ];
            $this->deliver('channels/supplier-feed', $feed, 'X-Signature', 'workbench-supplier-secret', ['X-Request-Id' => 'nw-20261001-0931']);
            $at('7 hours');
            $this->deliver('channels/supplier-feed', ['supplier' => 'Northwind Traders', 'items' => []], 'X-Signature', 'not-the-secret', ['X-Request-Id' => 'nw-forged-1']);
            $at('6 hours');
            $this->deliver('channels/storefront-webhooks', ['type' => 'order.created', 'order' => 'SO-88213', 'total' => 129.5], 'X-Storefront-Signature', 'workbench-storefront-secret');
            $at('5 hours');
            $this->deliver('erp/notify', ['event' => 'invoice.posted', 'invoice' => 'INV-40021'], 'X-Signature', 'whatever');

            // The scheduled sweep fires PO-1035's three-day timeout.
            $at('4 hours');
            Artisan::call('impex:tick');

            $at('3 hours');
            $this->scheduled('order-export');

            // Waiting for someone to approve it from the run page.
            $at('2 hours');
            $this->start('purchase-approval', ['PO-1042', 'Wayne Hardware', 4800], $test);

            // In flight: the file is read, the write is queued.
            $at('40 minutes');
            $this->held(function () use ($ada): void {
                $this->advance(
                    $this->start('customer-import', ['customers-2026-10.csv'], $ada),
                    fn (RunModel $run): bool => $run->steps()->where('status', StepStatus::Completed)->exists()
                        && $run->steps()->where('status', StepStatus::Pending)->exists(),
                );
            });

            // The CRM refused it and the import is being undone.
            $at('25 minutes');
            $this->held(function () use ($grace): void {
                $this->advance(
                    $this->start('customer-import', ['customers-legacy-emea.csv'], $grace),
                    fn (RunModel $run): bool => $run->status === RunStatus::RollingBack,
                );
            });

            // Started, not yet picked up by a worker.
            $at('5 minutes');
            $this->held(fn (): RunModel => $this->start('order-export', [], $test));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Start a flow as its owner would from the Flows page.
     *
     * @param  array<int, mixed>  $arguments
     */
    private function start(string $flow, array $arguments, Model $owner): RunModel
    {
        return app(RunFlowAction::class)->execute($flow, ['arguments' => $arguments], owner: $owner);
    }

    /**
     * Start a flow as the scheduler does.
     */
    private function scheduled(string $flow): void
    {
        Artisan::call('impex:run', ['flow' => $flow, '--trigger' => 'schedule']);
    }

    /**
     * Answer a run's approval wait.
     *
     * @param  array<string, mixed>  $payload
     */
    private function signal(RunModel $run, array $payload): void
    {
        app(SignalRunAction::class)->execute($run, ['name' => 'approval', 'payload' => $payload]);
    }

    /**
     * Send a signed request to an inbound channel endpoint.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function deliver(string $path, array $payload, string $signatureHeader, string $secret, array $headers = []): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ([$signatureHeader => hash_hmac('sha256', $body, $secret), ...$headers] as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $prefix = config('impex.routes.prefix');

        app(Kernel::class)->handle(Request::create("/{$prefix}/{$path}", 'POST', server: $server, content: $body));
    }

    /**
     * Hold the engine's jobs while the callback runs, so a run stays where
     * the callback leaves it rather than being driven to the end.
     */
    private function held(Closure $callback): void
    {
        config([
            'queue.connections.'.self::HELD => ['driver' => 'null'],
            'impex.queue.connection' => self::HELD,
        ]);

        try {
            $callback();
        } finally {
            config(['impex.queue.connection' => null]);
        }
    }

    /**
     * Drive a held run one step at a time, as a worker would, until it
     * reaches the state the demo wants to show.
     *
     * @param  Closure(RunModel): bool  $reached
     */
    private function advance(RunModel $run, Closure $reached): void
    {
        $engine = app(Engine::class);

        for ($i = 0; $i < 20 && ! $reached($run->refresh()); $i++) {
            $engine->drive((string) $run->getKey());

            if ($reached($run->refresh())) {
                return;
            }

            $step = $run->steps()
                ->where('status', StepStatus::Pending)
                ->orderBy('phase')
                ->orderBy('sequence')
                ->first();

            if ($step instanceof RunStepModel) {
                $engine->executeStep((string) $run->getKey(), $step->phase->value, $step->sequence);
            }
        }
    }
}
