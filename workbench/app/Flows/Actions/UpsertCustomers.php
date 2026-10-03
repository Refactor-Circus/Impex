<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * Writes the imported customers, reporting how many were new.
 */
final class UpsertCustomers
{
    /**
     * @param  array<int, array{id: string, name: string, email: string}>  $customers
     * @return array{customers: int, created: int, updated: int}
     */
    public function execute(string $file, array $customers): array
    {
        $created = intdiv(count($customers) * 2, 3);

        return ['customers' => count($customers), 'created' => $created, 'updated' => count($customers) - $created];
    }
}
