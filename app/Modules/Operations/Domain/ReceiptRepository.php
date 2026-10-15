<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

interface ReceiptRepository
{
    /** ¿Ya hay una recepción NO anulada con el mismo documento del proveedor? */
    public function activeDocumentExists(int $supplierId, DocumentType $type, string $number): bool;

    public function insertReceipt(NewReceipt $receipt, string $code): int;

    /** @param list<ReceiptLine> $lines */
    public function replaceReceiptItems(int $receiptId, array $lines): void;

    /** Bloquea la recepción (FOR UPDATE) con sus líneas. */
    public function lockReceipt(int $id): ?ReceiptRecord;

    public function setItemMovement(int $itemId, int $movementId): void;

    public function setReceiptStatus(int $id, ReceiptStatus $status, ?\DateTimeImmutable $confirmedAt = null): void;
}
