<?php

declare(strict_types=1);

namespace Workbench\App\Flows;

use RefactorCircus\Impex\Domains\Flow\Support\Flow;
use Workbench\App\Flows\Actions\PushCustomersToCrm;
use Workbench\App\Flows\Actions\ReadCustomerFile;
use Workbench\App\Flows\Actions\RemoveImportedCustomers;
use Workbench\App\Flows\Actions\UpsertCustomers;

/**
 * Imports a customer CSV, then mirrors it into the CRM.
 *
 * Any file with "legacy" in its name carries a malformed email address that
 * the CRM refuses, so the import is undone and the run fails.
 */
final class ImportCustomersFlow extends Flow
{
    /**
     * @return array<string, mixed>
     */
    public function handle(string $file = 'customers-2026-10.csv'): array
    {
        $this->tag('file', $file);

        $customers = $this->action(ReadCustomerFile::class, $file)->run();

        $saved = $this->action(UpsertCustomers::class, $file, $customers)
            ->undoWith(RemoveImportedCustomers::class, $file)
            ->run();

        $crm = $this->action(PushCustomersToCrm::class, (string) $this->context()->run->getKey(), $customers)
            ->tries(2)
            ->run();

        return ['file' => $file, ...$saved, 'crm_accepted' => $crm['accepted']];
    }
}
