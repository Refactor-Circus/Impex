<?php

declare(strict_types=1);

namespace Workbench\App\Flows\Actions;

use RefactorCircus\Impex\Impex;

/**
 * Drops the export on the partner's SFTP server and records the file in the
 * ledger, which the HTTP recorder cannot see.
 */
final class DeliverExport
{
    public function __construct(private readonly Impex $impex) {}

    /**
     * @return array{path: string, bytes: int}
     */
    public function execute(string $runId, string $filename, string $csv): array
    {
        $path = 'sftp://partner.example.test/inbound/'.$filename;

        $this->impex->record(
            channel: 'partner-sftp',
            endpoint: $path,
            body: $csv,
            transport: 'sftp',
            runId: $runId,
            headers: ['content-type' => ['text/csv']],
        );

        return ['path' => $path, 'bytes' => strlen($csv)];
    }
}
