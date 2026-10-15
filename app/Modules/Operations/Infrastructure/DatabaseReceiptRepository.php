<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\ReceiptItemRecord;
use App\Modules\Operations\Domain\ReceiptLine;
use App\Modules\Operations\Domain\ReceiptRecord;
use App\Modules\Operations\Domain\ReceiptRepository;
use App\Modules\Operations\Domain\ReceiptStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DatabaseReceiptRepository implements ReceiptRepository
{
    public function activeDocumentExists(int $supplierId, DocumentType $type, string $number): bool
    {
        return DB::table('purchase_receipts')
            ->where('supplier_id', $supplierId)->where('doc_type', $type->value)->where('doc_number', $number)
            ->where('status', '<>', ReceiptStatus::Cancelled->value)->exists();
    }

    public function insertReceipt(NewReceipt $receipt, string $code): int
    {
        try {
            $id = (int) DB::table('purchase_receipts')->insertGetId([
                'code' => $code,
                'supplier_id' => $receipt->supplierId,
                'warehouse_id' => $receipt->warehouseId,
                'doc_type' => $receipt->docType->value,
                'doc_number' => $receipt->docNumber,
                'doc_date' => $receipt->docDate,
                'status' => ReceiptStatus::Draft->value,
                'notes' => $receipt->notes,
                'received_by' => $receipt->receivedBy,
            ], 'id');
        } catch (UniqueConstraintViolationException) {
            // Carrera entre dos registros del mismo documento: la BD es la última defensa.
            throw OperationsException::duplicateDocument($receipt->docType->value, $receipt->docNumber);
        }

        $this->insertItems($id, $receipt->lines);

        return $id;
    }

    public function replaceReceiptItems(int $receiptId, array $lines): void
    {
        DB::table('purchase_receipt_items')->where('receipt_id', $receiptId)->delete();
        $this->insertItems($receiptId, $lines);
    }

    public function lockReceipt(int $id): ?ReceiptRecord
    {
        $r = DB::table('purchase_receipts')->where('id', $id)->lockForUpdate()->first();
        if ($r === null) {
            return null;
        }

        $items = [];
        foreach (DB::table('purchase_receipt_items')->where('receipt_id', $id)->orderBy('id')->get() as $i) {
            $items[] = new ReceiptItemRecord(
                (int) $i->id,
                new ReceiptLine(
                    (int) $i->product_id,
                    (int) $i->location_id,
                    Quantity::of((string) $i->qty_received),
                    UnitCost::of((string) $i->unit_cost),
                    $i->qty_expected === null ? null : Quantity::of((string) $i->qty_expected),
                    $i->presentation_id === null ? null : (int) $i->presentation_id,
                    $i->presentation_qty === null ? null : Quantity::of((string) $i->presentation_qty),
                ),
                $i->movement_id === null ? null : (int) $i->movement_id,
            );
        }

        return new ReceiptRecord(
            (int) $r->id, (string) $r->code, ReceiptStatus::from((string) $r->status),
            (int) $r->supplier_id, (int) $r->warehouse_id, (string) $r->doc_type, (string) $r->doc_number, $items,
        );
    }

    public function setItemMovement(int $itemId, int $movementId): void
    {
        DB::table('purchase_receipt_items')->where('id', $itemId)->update(['movement_id' => $movementId]);
    }

    public function setReceiptStatus(int $id, ReceiptStatus $status, ?\DateTimeImmutable $confirmedAt = null): void
    {
        DB::table('purchase_receipts')->where('id', $id)->update([
            'status' => $status->value,
            'confirmed_at' => $confirmedAt?->format('Y-m-d H:i:sP'),
            'updated_at' => now(),
        ]);
    }

    /** @param list<ReceiptLine> $lines */
    private function insertItems(int $receiptId, array $lines): void
    {
        DB::table('purchase_receipt_items')->insert(array_map(static fn (ReceiptLine $l): array => [
            'receipt_id' => $receiptId,
            'product_id' => $l->productId,
            'presentation_id' => $l->presentationId,
            'presentation_qty' => $l->presentationQty?->value,
            'qty_expected' => $l->expected?->value,
            'qty_received' => $l->quantity->value,
            'unit_cost' => $l->unitCost->value,
            'location_id' => $l->locationId,
        ], $lines));
    }
}
