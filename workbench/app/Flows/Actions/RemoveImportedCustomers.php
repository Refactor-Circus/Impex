<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

/**
 * The rollback for UpsertCustomers: removes what one file imported.
 */
final class RemoveImportedCustomers
{
    /**
     * @return array{file: string, removed: bool}
     */
    public function execute(string $file): array
    {
        return ['file' => $file, 'removed' => true];
    }
}
