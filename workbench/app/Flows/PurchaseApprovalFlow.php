<?php

declare(strict_types=1);

namespace Workbench\App\Flows;

use JayI\Impex\Flows\Flow;
use Workbench\App\Flows\Actions\DraftPurchaseOrder;
use Workbench\App\Flows\Actions\SubmitPurchaseOrder;
use Workbench\App\Flows\Actions\VoidPurchaseOrder;

/**
 * Drafts a purchase order and waits for someone to approve it before sending
 * it to the supplier.
 *
 * Approve from the run page with the signal `approval` and a payload such as
 * {"approved": true, "by": "ada@example.com"}.
 */
final class PurchaseApprovalFlow extends Flow
{
    /**
     * @return array<string, mixed>
     */
    public function handle(string $order = 'PO-1040', string $supplier = 'Northwind Traders', int $amount = 4800): array
    {
        $this->tag('order', $order);
        $this->tag('supplier', $supplier);

        $draft = $this->action(DraftPurchaseOrder::class, $order, $supplier, $amount)
            ->undoWith(VoidPurchaseOrder::class, $order)
            ->run();

        /** @var array{approved?: bool, by?: string, reason?: string} $decision */
        $decision = $this->signal('approval')
            ->timeoutAfter(now()->addDays(3))
            ->default(['approved' => false, 'reason' => 'No decision within three days.'])
            ->wait();

        if (! ($decision['approved'] ?? false)) {
            return ['order' => $order, 'approved' => false, 'reason' => $decision['reason'] ?? null];
        }

        $submitted = $this->action(SubmitPurchaseOrder::class, (string) $this->context()->run->getKey(), $draft)
            ->tries(3)
            ->run();

        return [
            'order' => $order,
            'approved' => true,
            'approved_by' => $decision['by'] ?? null,
            'supplier_reference' => $submitted['reference'],
        ];
    }
}
