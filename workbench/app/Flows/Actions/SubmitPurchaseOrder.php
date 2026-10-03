<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

use JayI\Impex\Impex;

/**
 * Sends an approved purchase order to the supplier, through the recorded
 * `supplier-api` channel.
 */
final class SubmitPurchaseOrder
{
    public function __construct(private readonly Impex $impex) {}

    /**
     * @param  array<string, mixed>  $draft
     * @return array{reference: string}
     */
    public function execute(string $runId, array $draft): array
    {
        $response = $this->impex->http('supplier-api', $runId)
            ->post('https://supplier.example.test/purchase-orders', $draft)
            ->throw();

        return ['reference' => (string) $response->json('reference')];
    }
}
