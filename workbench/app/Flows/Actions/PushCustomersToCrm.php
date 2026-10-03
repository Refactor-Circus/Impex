<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

use JayI\Impex\Impex;
use RuntimeException;

/**
 * Mirrors the customers into the CRM, through the recorded `crm-api` channel.
 */
final class PushCustomersToCrm
{
    public function __construct(private readonly Impex $impex) {}

    /**
     * @param  array<int, array{id: string, name: string, email: string}>  $customers
     * @return array{accepted: int}
     */
    public function execute(string $runId, array $customers): array
    {
        $response = $this->impex->http('crm-api', $runId)
            ->post('https://crm.example.test/v2/customers/bulk', ['customers' => $customers]);

        if ($response->failed()) {
            throw new RuntimeException('The CRM rejected the import. '.$response->json('message'));
        }

        return ['accepted' => (int) $response->json('accepted')];
    }
}
